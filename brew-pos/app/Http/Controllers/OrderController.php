<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->with(['cashier', 'table', 'items.product', 'payment'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->date, fn($q) => $q->whereDate('created_at', $request->date))
            ->when($request->today, fn($q) => $q->whereDate('created_at', today()))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'table_id' => 'nullable|exists:tables,id',
            'order_type' => 'required|in:dine_in,takeout,delivery',
            'customer_name' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string',
        ]);

        $data['cashier_id'] = $request->user()->id;

        $order = $this->orderService->createOrder($data);

        return response()->json($order->load(['cashier', 'table', 'items.product', 'payment']), 201);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json($order->load(['cashier', 'table', 'items.product', 'payment']));
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:pending,preparing,ready,completed,cancelled',
        ]);

        $order = $this->orderService->updateStatus($order, $data['status']);

        return response()->json($order->load(['cashier', 'table', 'items.product', 'payment']));
    }

    public function kitchenOrders(): JsonResponse
    {
        $orders = Order::with(['table', 'items.product'])
            ->whereIn('status', ['pending', 'preparing'])
            ->orderBy('created_at')
            ->get();

        return response()->json($orders);
    }

    public function destroy(Order $order): JsonResponse
    {
        if ($order->status === 'completed') {
            return response()->json(['message' => 'Cannot delete a completed order.'], 422);
        }

        $order->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Order cancelled.']);
    }
}
