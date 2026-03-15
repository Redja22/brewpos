<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function processPayment(Order $order, array $data): Payment
    {
        return DB::transaction(function () use ($order, $data) {
            $change = max(0, $data['amount_tendered'] - $order->total_amount);

            $payment = Payment::create([
                'order_id'         => $order->id,
                'method'           => $data['method'],
                'amount_tendered'  => $data['amount_tendered'],
                'change_amount'    => $change,
                'reference_number' => $data['reference_number'] ?? null,
                'status'           => 'completed',
                'processed_at'     => now(),
            ]);

            // ✅ DO NOT change order status here.
            // Order stays in its current status (preparing/ready).
            // Kitchen is responsible for marking it as completed.
            // Payment just records that the customer has paid.

            return $payment;
        });
    }
}
