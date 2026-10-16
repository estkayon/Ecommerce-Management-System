<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    public function adjustStock(
        int $productId,
        int $quantityChange,
        string $reason,
        string $type = 'adjustment',
        ?string $referenceType = null,
        ?int $referenceId = null
    ): Inventory {
        if ($quantityChange === 0) {
            throw new InvalidArgumentException('Stock change cannot be zero.');
        }

        return DB::transaction(function () use (
            $productId,
            $quantityChange,
            $reason,
            $type,
            $referenceType,
            $referenceId
        ) {
            // Lock the inventory row to prevent concurrent stock updates.
            $inventory = Inventory::where('product_id', $productId)
                ->lockForUpdate()
                ->firstOrFail();

            $before = $inventory->quantity;
            $after = $before + $quantityChange;

            if ($after < 0) {
                throw new InvalidArgumentException('Insufficient stock.');
            }

            $inventory->quantity = $after;
            $inventory->save();

            // Record the stock change within the same transaction.
            InventoryMovement::create([
                'product_id' => $productId,
                'quantity_change' => $quantityChange,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'type' => $type,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);

            return $inventory->refresh();
        }, 3);
    }
}
