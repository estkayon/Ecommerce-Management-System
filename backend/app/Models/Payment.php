<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'gateway',
        'transaction_id',
        'gateway_payment_id',
        'amount',
        'currency',
        'status',
        'verified_at',
        'paid_at',
        'metadata',
        'failure_reason',
        'last_checked_at',
        'reconciliation_required_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
            'verified_at' => 'datetime',
            'paid_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'reconciliation_required_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
