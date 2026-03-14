<?php

namespace App\Services;

use App\Models\Order;
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
            $settings  = Cache::get('app_settings', []);
            $taxRate   = ($settings['tax_enabled'] ?? true) ? ($settings['tax_rate'] ?? 12) : 0;

            $subtotal  = 0;
            $itemsData = [];

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
                // Default: preparing (cashier just placed the order)
                'status'          => 'preparing',
                'subtotal'        => $subtotal,
                'tax_amount'      => $taxAmount,
                'discount_amount' => 0,
                'total_amount'    => $totalAmount,
            ]);

            foreach ($itemsData as $itemData) {
                $order->items()->create($itemData);
            }

            // Deduct inventory
            foreach ($data['items'] as $item) {
                $product = Product::find($item['product_id']);
                if ($product->track_inventory && $product->inventory) {
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

            // ✅ Mark table as occupied when order is created
            if ($order->table_id) {
                Table::where('id', $order->table_id)
                    ->update(['status' => 'occupied']);
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

        // Table availability will be managed manually by staff via Tables page.

        return $order->fresh();
    }
}
