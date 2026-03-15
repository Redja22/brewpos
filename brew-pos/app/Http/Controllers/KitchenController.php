<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class KitchenController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    /**
     * Returns:
     * - All preparing orders (any date)
     * - All ready orders (any date)
     * - Today's completed orders (for reference)
     */
    public function index(): JsonResponse
    {
        // Active orders: preparing + ready (all dates, in case of old unfinished orders)
        $active = Order::with(['table', 'items'])
            ->whereIn('status', ['preparing', 'ready'])
            ->orderBy('created_at')
            ->get();

        // Completed: today only, newest first
        $completed = Order::with(['table', 'items'])
            ->where('status', 'completed')
            ->whereDate('created_at', today())
            ->orderByDesc('created_at')
            ->get();

        // Merge: active first, then completed
        $orders = $active->concat($completed)->values();

        return response()->json($orders);
    }

    /**
     * preparing → ready
     */
    public function markReady(Order $order): JsonResponse
    {
        if ($order->status !== 'preparing') {
            return response()->json([
                'message' => "Order must be 'preparing' to mark as ready. Current status: {$order->status}",
            ], 422);
        }

        $order = $this->orderService->updateStatus($order, 'ready');

        return response()->json($order->load(['table', 'items']));
    }

    /**
     * ready → completed
     */
    public function markCompleted(Order $order): JsonResponse
    {
        if ($order->status !== 'ready') {
            return response()->json([
                'message' => "Order must be 'ready' to mark as completed. Current status: {$order->status}",
            ], 422);
        }

        $order = $this->orderService->updateStatus($order, 'completed');

        return response()->json($order->load(['table', 'items']));
    }
}
