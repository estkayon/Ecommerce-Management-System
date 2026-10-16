<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with('inventory')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->query('search');

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('sku', 'ilike', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15);

        return response()->json($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        // Create the product and its inventory record atomically.
        $product = DB::transaction(function () use ($request) {
            $product = Product::create($request->validated());

            $product->inventory()->create([
                'quantity' => 0,
            ]);

            return $product;
        });

        return response()->json([
            'message' => 'Product created successfully.',
            'data' => $product->load('inventory'),
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'data' => $product->load('inventory'),
        ]);
    }

    public function update(
        UpdateProductRequest $request,
        Product $product
    ): JsonResponse {
        $product->update($request->validated());

        return response()->json([
            'message' => 'Product updated successfully.',
            'data' => $product->load('inventory'),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        // Soft-delete the product while preserving its historical records.
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }
}
