<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    // Enable soft deletion to preserve historical product records.
    use SoftDeletes;

    /**
     * Attributes allowed for mass assignment.
     */
    protected $fillable = [
        'name',
        'sku',
        'description',
        'price',
        'is_active',
    ];

    /**
     * Convert database attributes into appropriate PHP types.
     */
    protected function casts(): array
    {
        return [
            // Keep prices as precise decimal strings.
            'price' => 'decimal:2',

            // Convert product status into a boolean.
            'is_active' => 'boolean',
        ];
    }

     public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
