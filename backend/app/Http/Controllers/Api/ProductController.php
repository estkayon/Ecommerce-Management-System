<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'string', 'max:100'],
        ]);

        $products = Product::query()
            ->where('is_active', true)
            ->whereHas('inventory', function ($query) {
                $query->where('quantity', '>', 0);
            })
            ->with('inventory:product_id,quantity')
            ->when(
            $validated['search'] ?? null,
            function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('sku', 'ilike', "%{$search}%");
                });
            }
        )
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 36);

        return response()->json($products);
    }

    public function show(Product $product): JsonResponse
    {
        if (
            ! $product->is_active ||
            ! $product->inventory ||
            $product->inventory->quantity <= 0
        ) {
            abort(404);
        }

        $product->load('inventory:product_id,quantity');

        return response()->json([
            'data' => $product,
        ]);
    }
}
