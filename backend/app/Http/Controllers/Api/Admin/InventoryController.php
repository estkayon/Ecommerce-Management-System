<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustInventoryRequest;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'data' => [
                'product' => $product->only(['id', 'name', 'sku']),
                'inventory' => $product->inventory,
                'movements' => $product->inventoryMovements()
                    ->latest('id')
                    ->paginate(20),
            ],
        ]);
    }

    public function adjust(
        AdjustInventoryRequest $request,
        Product $product
    ): JsonResponse {
        try {
            $inventory = $this->inventoryService->adjustStock(
                productId: $product->id,
                quantityChange: $request->integer('quantity_change'),
                reason: $request->validated('reason')
            );

            return response()->json([
                'message' => 'Inventory updated successfully.',
                'data' => $inventory,
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
