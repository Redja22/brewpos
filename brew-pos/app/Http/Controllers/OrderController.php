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
            ->with(['cashier', 'table', 'items', 'payment'])
            ->when($request->status, function ($q) use ($request) {
                $statuses = explode(',', $request->status);
                $q->whereIn('status', $statuses);
            })
            ->when($request->date, fn($q) => $q->whereDate('created_at', $request->date))
            ->when($request->today, fn($q) => $q->whereDate('created_at', today()))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 25);

        return response()->json($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'table_id'           => 'nullable|exists:tables,id',
            'order_type'         => 'required|in:dine_in,takeout,delivery',
            'customer_name'      => 'nullable|string|max:100',
            'notes'              => 'nullable|string',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.notes'      => 'nullable|string',
        ]);

        $data['cashier_id'] = $request->user()->id;
        $order = $this->orderService->createOrder($data);

        return response()->json(
            $order->load(['cashier', 'table', 'items', 'payment']),
            201
        );
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json(
            $order->load(['cashier', 'table', 'items', 'payment'])
        );
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:pending,preparing,ready,completed,cancelled',
        ]);

        $order = $this->orderService->updateStatus($order, $data['status']);

        return response()->json(
            $order->load(['cashier', 'table', 'items', 'payment'])
        );
    }

    /**
     * Cancel an order (cashier side).
     *
     * - Completed orders cannot be cancelled.
     * - If order already has a payment, a refund record is automatically created.
     * - Inventory is restored for tracked products.
     *
     * Response includes refund_issued and refund_amount so the
     * frontend can prompt the cashier to return cash to the customer.
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        if ($order->status === 'completed') {
            return response()->json([
                'message' => 'Cannot cancel a completed order.',
            ], 422);
        }

        if ($order->status === 'cancelled') {
            return response()->json([
                'message' => 'Order is already cancelled.',
            ], 422);
        }

        $result = $this->orderService->cancelOrder($order, $request->user()->id);

        return response()->json([
            'message'       => 'Order cancelled successfully.',
            'order'         => $result['order']->load(['cashier', 'table', 'items', 'payment']),
            'refund_issued' => $result['refund_issued'],
            'refund_amount' => $result['refund_amount'],
        ]);
    }
}
