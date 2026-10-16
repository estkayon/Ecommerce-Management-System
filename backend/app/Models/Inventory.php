<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    /**
     * Define attributes that can be mass-assigned.
     * Stock changes will later be controlled through a dedicated service.
     */
    protected $fillable = [
        'product_id',
    ];

    /**
     * Cast database values into appropriate PHP types.
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /**
     * Each inventory record belongs to one product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
