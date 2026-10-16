<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    protected $fillable = [
        'order_id',
        'provider',
        'status',
        'consignment_id',
        'tracking_number',
        'attempts',
        'last_error',
        'dispatched_at',
        'delivered_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'metadata' => 'array',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
