<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $paymentService) {}

    public function process(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'method' => 'required|in:cash,card,gcash',
            'amount_tendered' => 'required|numeric|min:0',
            'reference_number' => 'nullable|string',
        ]);

        $order = Order::findOrFail($data['order_id']);

        if ($order->payment) {
            return response()->json(['message' => 'Order already paid.'], 422);
        }

        $payment = $this->paymentService->processPayment($order, $data);

        return response()->json([
            'payment' => $payment,
            'order' => $order->fresh()->load(['items.product', 'payment', 'table']),
            'change' => $payment->change_amount,
        ]);
    }
}
