<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Table extends Model
{
    protected $fillable = [
        'name',
        'number',
        'capacity',
        'status',
        'position_x',
        'position_y',
        'width',
        'height',
        'shape',
        'rotation',
        'notes',
    ];

    protected $appends = ['computed_status'];

    // status: available, occupied, reserved, inactive
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function activeOrder()
    {
        return $this->hasOne(Order::class)->whereIn('status', ['pending', 'preparing', 'ready']);
    }

    public function getComputedStatusAttribute(): string
    {
        return $this->activeOrder ? 'occupied' : ($this->status ?? 'available');
    }
}
