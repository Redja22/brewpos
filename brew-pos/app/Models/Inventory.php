<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $fillable = [
        'product_id',
        'quantity',
        'low_stock_threshold',
        'unit',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'low_stock_threshold' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function logs()
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->low_stock_threshold;
    }
}
