<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'cashier_id',
        'table_id',
        'status',
        'order_type',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'customer_name',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    // status: pending, preparing, ready, completed, cancelled
    // order_type: dine_in, takeout, delivery

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($order) {
            $order->order_number = static::generateOrderNumber();
        });
    }

    public static function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $date = now()->format('Ymd');
        $last = static::whereDate('created_at', today())->count() + 1;
        return $prefix . $date . str_pad($last, 4, '0', STR_PAD_LEFT);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
}
