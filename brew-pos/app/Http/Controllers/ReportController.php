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
        $date   = $request->date ?? today()->toDateString();
        $period = $request->period ?? 'today'; // today, week, month

        $driver = DB::getDriverName();
        $hourExpr = $driver === 'sqlite'
            ? "strftime('%H', created_at)"
            : "HOUR(created_at)";
        $dateExpr = $driver === 'sqlite'
            ? "date(created_at)"
            : "DATE(created_at)";

        $dateRange = match ($period) {
            'week'  => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            default => [today()->startOfDay(), today()->endOfDay()],
        };

        $completedOrders = Order::where('status', 'completed')
            ->whereBetween('created_at', $dateRange);

        $totalRevenue = (clone $completedOrders)->sum('total_amount');
        $totalOrders  = (clone $completedOrders)->count();
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        // Hourly sales for today
        $hourlyRaw = Order::where('status', 'completed')
            ->whereDate('created_at', today())
            ->selectRaw("$hourExpr as hour, SUM(total_amount) as revenue, COUNT(*) as orders")
            ->groupByRaw($hourExpr)
            ->orderBy('hour')
            ->get();
        // Normalize to 24 hours (0-23) so charts always render
        $hourlySales = collect(range(0, 23))->map(function ($h) use ($hourlyRaw) {
            $hit = $hourlyRaw->firstWhere('hour', sprintf('%02d', $h)) ?? $hourlyRaw->firstWhere('hour', $h);
            return [
                'hour'    => $h,
                'revenue' => $hit->revenue ?? 0,
                'orders'  => $hit->orders ?? 0,
            ];
        });

        // Top products
        $topProducts = OrderItem::with('product')
            ->whereHas('order', fn($q) => $q->where('status', 'completed')->whereBetween('created_at', $dateRange))
            ->selectRaw('product_id, product_name, SUM(quantity) as total_qty, SUM(subtotal) as total_revenue')
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        // Payment method breakdown
        $paymentBreakdown = Payment::whereHas('order', fn($q) => $q->where('status', 'completed')->whereBetween('created_at', $dateRange))
            ->selectRaw('method, COUNT(*) as count, SUM(amount_tendered) as total')
            ->groupBy('method')
            ->get();

        // Recent orders
        $recentOrders = Order::with(['cashier', 'items', 'payment'])
            ->where('status', 'completed')
            ->whereDate('created_at', today())
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Daily sales for the last 7 days
        $dailyRaw = Order::where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw("$dateExpr as date, SUM(total_amount) as revenue, COUNT(*) as orders")
            ->groupByRaw($dateExpr)
            ->orderBy('date')
            ->get();
        $dates = collect(range(0, 6))->map(fn ($i) => now()->subDays(6 - $i)->toDateString());
        $dailySales = $dates->map(function ($d) use ($dailyRaw) {
            $hit = $dailyRaw->firstWhere('date', $d);
            return [
                'date'    => $d,
                'revenue' => $hit->revenue ?? 0,
                'orders'  => $hit->orders ?? 0,
            ];
        });

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
        ]);
    }

    public function salesByDate(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $driver = DB::getDriverName();
        $dateExpr = $driver === 'sqlite'
            ? "date(created_at)"
            : "DATE(created_at)";

        $sales = Order::where('status', 'completed')
            ->whereBetween(DB::raw($dateExpr), [$request->start_date, $request->end_date])
            ->selectRaw("$dateExpr as date, SUM(total_amount) as revenue, SUM(tax_amount) as tax, COUNT(*) as orders")
            ->groupByRaw($dateExpr)
            ->orderBy('date')
            ->get();

        return response()->json($sales);
    }
}
