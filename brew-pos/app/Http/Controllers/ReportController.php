<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $period = $request->period ?? 'today';

        $driver   = DB::getDriverName();
        $hourExpr = $driver === 'sqlite' ? "strftime('%H', created_at)" : "HOUR(created_at)";
        $dateExpr = $driver === 'sqlite' ? "date(created_at)"           : "DATE(created_at)";

        $dateRange = match ($period) {
            'week'  => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            default => [today()->startOfDay(), today()->endOfDay()],
        };

        // ── Base query: completed orders only, never cancelled ────────────────
        $completedOrders = Order::where('status', 'completed')
            ->whereBetween('created_at', $dateRange);

        $totalRevenue  = (clone $completedOrders)->sum('total_amount');
        $totalOrders   = (clone $completedOrders)->count();
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        // ── Hourly sales (today only) ─────────────────────────────────────────
        $hourlyRaw = Order::where('status', 'completed')
            ->whereDate('created_at', today())
            ->selectRaw("$hourExpr as hour, SUM(total_amount) as revenue, COUNT(*) as orders")
            ->groupByRaw($hourExpr)
            ->orderBy('hour')
            ->get();

        $hourlySales = collect(range(0, 23))->map(function ($h) use ($hourlyRaw) {
            $hit = $hourlyRaw->firstWhere('hour', sprintf('%02d', $h))
                ?? $hourlyRaw->firstWhere('hour', $h);
            return [
                'hour'    => $h,
                'revenue' => $hit->revenue ?? 0,
                'orders'  => $hit->orders  ?? 0,
            ];
        });

        // ── Top products (completed orders only) ──────────────────────────────
        $topProducts = OrderItem::with('product')
            ->whereHas('order', fn($q) => $q
                ->where('status', 'completed')
                ->whereBetween('created_at', $dateRange)
            )
            ->selectRaw('product_id, product_name, SUM(quantity) as total_qty, SUM(subtotal) as total_revenue')
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        // ── Payment breakdown ─────────────────────────────────────────────────
        // Only count payments that are 'completed' (not 'refunded').
        // This ensures cancelled orders with refunds don't inflate the numbers.
        $paymentBreakdown = Payment::where('status', 'completed')
            ->whereHas('order', fn($q) => $q
                ->where('status', 'completed')
                ->whereBetween('created_at', $dateRange)
            )
            ->selectRaw('method, COUNT(*) as count, SUM(amount_tendered) as total')
            ->groupBy('method')
            ->get();

        // ── Recent completed orders (today) ───────────────────────────────────
        $recentOrders = Order::with(['cashier', 'items', 'payment'])
            ->where('status', 'completed')
            ->whereDate('created_at', today())
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // ── Daily / weekly / monthly sales chart ──────────────────────────────
        $dailySales = match ($period) {

            'month' => (function () use ($driver) {
                $monthExpr = $driver === 'sqlite'
                    ? "strftime('%m', created_at)"
                    : "MONTH(created_at)";

                $raw = Order::where('status', 'completed')
                    ->whereYear('created_at', now()->year)
                    ->selectRaw("$monthExpr as month, SUM(total_amount) as revenue, COUNT(*) as orders")
                    ->groupByRaw($monthExpr)
                    ->orderBy('month')
                    ->get();

                return collect(range(1, now()->month))->map(function ($m) use ($raw) {
                    $hit = $raw->firstWhere('month', $m)
                        ?? $raw->firstWhere('month', sprintf('%02d', $m));
                    return [
                        'date'    => $m,
                        'revenue' => $hit->revenue ?? 0,
                        'orders'  => $hit->orders  ?? 0,
                    ];
                });
            })(),

            'week' => (function () use ($dateExpr) {
                $start = now()->startOfWeek();
                $raw   = Order::where('status', 'completed')
                    ->whereBetween('created_at', [$start, now()->endOfWeek()])
                    ->selectRaw("$dateExpr as date, SUM(total_amount) as revenue, COUNT(*) as orders")
                    ->groupByRaw($dateExpr)
                    ->orderBy('date')
                    ->get();

                return collect(range(0, 6))->map(function ($i) use ($start, $raw) {
                    $ds  = $start->copy()->addDays($i)->toDateString();
                    $hit = $raw->firstWhere('date', $ds);
                    return [
                        'date'    => $ds,
                        'revenue' => $hit->revenue ?? 0,
                        'orders'  => $hit->orders  ?? 0,
                    ];
                });
            })(),

            default => (function () use ($dateExpr) {
                $raw   = Order::where('status', 'completed')
                    ->where('created_at', '>=', now()->subDays(6)->startOfDay())
                    ->selectRaw("$dateExpr as date, SUM(total_amount) as revenue, COUNT(*) as orders")
                    ->groupByRaw($dateExpr)
                    ->orderBy('date')
                    ->get();

                return collect(range(0, 6))->map(function ($i) use ($raw) {
                    $d   = now()->subDays(6 - $i)->toDateString();
                    $hit = $raw->firstWhere('date', $d);
                    return [
                        'date'    => $d,
                        'revenue' => $hit->revenue ?? 0,
                        'orders'  => $hit->orders  ?? 0,
                    ];
                });
            })(),
        };

        return response()->json([
            'summary' => [
                'total_revenue'   => round($totalRevenue, 2),
                'total_orders'    => $totalOrders,
                'avg_order_value' => round($avgOrderValue, 2),
                'period'          => $period,
            ],
            'hourly_sales'      => $hourlySales,
            'top_products'      => $topProducts,
            'payment_breakdown' => $paymentBreakdown,
            'recent_orders'     => $recentOrders,
            'daily_sales'       => $dailySales,
            'daily_sales_label' => match ($period) {
                'month' => 'Monthly Sales (This Year)',
                'week'  => 'Daily Sales (This Week)',
                default => 'Daily Sales (Last 7 Days)',
            },
        ]);
    }

    public function salesByDate(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $driver   = DB::getDriverName();
        $dateExpr = $driver === 'sqlite' ? "date(created_at)" : "DATE(created_at)";

        $sales = Order::where('status', 'completed')
            ->whereBetween(DB::raw($dateExpr), [$request->start_date, $request->end_date])
            ->selectRaw("$dateExpr as date, SUM(total_amount) as revenue, SUM(tax_amount) as tax, COUNT(*) as orders")
            ->groupByRaw($dateExpr)
            ->orderBy('date')
            ->get();

        return response()->json($sales);
    }
}
