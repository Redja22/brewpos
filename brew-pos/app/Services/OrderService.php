<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class OrderService
{
    public function __construct(private InventoryService $inventoryService) {}

    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $settings    = Cache::get('app_settings', []);
            $taxRate     = ($settings['tax_enabled'] ?? true) ? ($settings['tax_rate'] ?? 12) : 0;
            $subtotal    = 0;
            $itemsData   = [];

            foreach ($data['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                if (!$product->is_available) {
                    throw new \Exception("Product '{$product->name}' is not available.");
                }

                $lineTotal   = $product->price * $item['quantity'];
                $subtotal   += $lineTotal;
                $itemsData[] = [
                    'product_id'    => $product->id,
                    'product_name'  => $product->name,
                    'product_price' => $product->price,
                    'quantity'      => $item['quantity'],
                    'subtotal'      => $lineTotal,
                    'notes'         => $item['notes'] ?? null,
                ];
            }

            $taxAmount   = round($subtotal * ($taxRate / 100), 2);
            $totalAmount = $subtotal + $taxAmount;

            $order = Order::create([
                'cashier_id'      => $data['cashier_id'],
                'table_id'        => $data['table_id'] ?? null,
                'order_type'      => $data['order_type'],
                'customer_name'   => $data['customer_name'] ?? null,
                'notes'           => $data['notes'] ?? null,
                'status'          => 'preparing',
                'subtotal'        => $subtotal,
                'tax_amount'      => $taxAmount,
                'discount_amount' => 0,
                'total_amount'    => $totalAmount,
            ]);

            foreach ($itemsData as $itemData) {
                $order->items()->create($itemData);
            }

            foreach ($data['items'] as $item) {
                $product = Product::find($item['product_id']);
                if ($product && $product->track_inventory && $product->inventory) {
                    $this->inventoryService->deduct(
                        $product->inventory,
                        $item['quantity'],
                        'sale',
                        "Order #{$order->order_number}",
                        $data['cashier_id'],
                        $order->id
                    );
                }
            }

            if ($order->table_id) {
                Table::where('id', $order->table_id)->update(['status' => 'occupied']);
            }

            return $order;
        });
    }

    public function updateStatus(Order $order, string $status): Order
    {
        $order->update([
            'status'       => $status,
            'completed_at' => $status === 'completed' ? now() : $order->completed_at,
        ]);

        return $order->fresh();
    }

    /**
     * Cancel an order.
     *
     * A) No payment → cancel + restore inventory.
     * B) Already paid → mark payment refunded + create refund record + restore inventory.
     *
     * @return array{order: Order, refund_issued: bool, refund_amount: float}
     */
    public function cancelOrder(Order $order, int $cancelledBy): array
    {
        return DB::transaction(function () use ($order, $cancelledBy) {
            $refundIssued = false;
            $refundAmount = 0;

            // Ensure relationships are loaded
            $order->loadMissing(['items', 'payment']);

            // ── Handle payment refund if already paid ─────────────────────────
            if ($order->payment && $order->payment->status === 'completed') {
                $refundAmount = (float) $order->payment->amount_tendered;

                $order->payment->update(['status' => 'refunded']);

                Payment::create([
                    'order_id'         => $order->id,
                    'method'           => $order->payment->method,
                    'amount_tendered'  => 0,
                    'change_amount'    => $refundAmount,
                    'reference_number' => 'REFUND-' . $order->order_number,
                    'status'           => 'refunded',
                    'processed_at'     => now(),
                ]);

                $refundIssued = true;
            }

            // ── Cancel the order ──────────────────────────────────────────────
            $order->update(['status' => 'cancelled']);

            // ── Restore inventory (only for tracked products) ─────────────────
            foreach ($order->items as $item) {
                // product_id can be null if product was deleted
                if (!$item->product_id) continue;

                $product = Product::find($item->product_id);
                if (!$product || !$product->track_inventory || !$product->inventory) continue;

                $this->inventoryService->restock(
                    $product->inventory,
                    $item->quantity,
                    'adjustment',
                    "Cancelled Order #{$order->order_number}",
                    $cancelledBy,
                    $order->id
                );
            }

            return [
                'order'         => $order->fresh(),
                'refund_issued' => $refundIssued,
                'refund_amount' => $refundAmount,
            ];
        });
    }
}
