<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryLog extends Model
{
    protected $fillable = [
        'inventory_id',
        'product_id',
        'type',
        'quantity_change',
        'quantity_before',
        'quantity_after',
        'reference_type',
        'reference_id',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'quantity_change' => 'integer',
        'quantity_before' => 'integer',
        'quantity_after' => 'integer',
    ];

    // type: sale, restock, adjustment, waste

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
