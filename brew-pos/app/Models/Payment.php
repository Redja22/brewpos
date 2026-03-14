<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'method',
        'amount_tendered',
        'change_amount',
        'reference_number',
        'status',
        'processed_at',
    ];

    protected $casts = [
        'amount_tendered' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    // method: cash, card, gcash
    // status: pending, completed, refunded

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
