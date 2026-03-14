<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'image',
        'sku',
        'is_active',
        'is_available',
        'track_inventory',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_available' => 'boolean',
        'track_inventory' => 'boolean',
    ];

    protected $appends = ['image_url', 'stock_quantity'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function inventory()
    {
        return $this->hasOne(Inventory::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) return null;
        return asset('storage/' . $this->image);
    }

    public function getStockQuantityAttribute(): int
    {
        return $this->inventory?->quantity ?? 0;
    }

    public function isInStock(): bool
    {
        if (!$this->track_inventory) return true;
        return $this->stockQuantity > 0;
    }
}
