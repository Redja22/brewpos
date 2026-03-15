<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryLog;

class InventoryService
{
    /**
     * Deduct stock (used for sales).
     */
    public function deduct(Inventory $inventory, int $quantity, string $type = 'sale', ?string $notes = null, ?int $userId = null, ?int $orderId = null): InventoryLog
    {
        return $this->adjust($inventory, -abs($quantity), $type, $notes, $userId, $orderId);
    }

    /**
     * Restock / restore inventory (used for cancellations, restocks).
     */
    public function restock(Inventory $inventory, int $quantity, string $type = 'adjustment', ?string $notes = null, ?int $userId = null, ?int $orderId = null): InventoryLog
    {
        return $this->adjust($inventory, abs($quantity), $type, $notes, $userId, $orderId);
    }

    /**
     * Adjust inventory by a positive or negative amount and create a log entry.
     */
    public function adjust(Inventory $inventory, int $quantityChange, string $type = 'adjustment', ?string $notes = null, ?int $userId = null, ?int $orderId = null): InventoryLog
    {
        $before = $inventory->quantity;
        $inventory->quantity = max(0, $before + $quantityChange);
        $inventory->save();

        return InventoryLog::create([
            'inventory_id'    => $inventory->id,
            'product_id'      => $inventory->product_id,
            'type'            => $type,
            'quantity_change' => $quantityChange,
            'quantity_before' => $before,
            'quantity_after'  => $inventory->quantity,
            'notes'           => $notes,
            'user_id'         => $userId,
            'reference_type'  => $orderId ? 'order' : null,
            'reference_id'    => $orderId,
        ]);
    }
}
