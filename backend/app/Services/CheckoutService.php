<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    public function checkout(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $items = collect($data['items'])->sortBy('product_id')->values();
            $orderItems = [];
            $subtotal = '0.00';

            // Lock inventory rows in a consistent order to reduce deadlocks.
            foreach ($items as $item) {
                $product = Product::query()
                    ->whereKey($item['product_id'])
                    ->where('is_active', true)
                    ->first();

                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => ['A selected product is unavailable.'],
                    ]);
                }

                $inventory = Inventory::where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                if (! $inventory || $inventory->quantity < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => ["Insufficient stock for {$product->name}."],
                    ]);
                }

                $unitPrice = $product->price;
                $lineTotal = bcmul($unitPrice, (string) $item['quantity'], 2);
                $subtotal = bcadd($subtotal, $lineTotal, 2);

                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'total_price' => $lineTotal,
                ];
            }

            // Create the order using server-calculated monetary values.
            $order = Order::create([
                'order_number' => 'ORD-'.Str::ulid(),
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_email' => $data['customer_email'] ?? null,
                'shipping_address' => $data['shipping_address'],
                'subtotal' => $subtotal,
                'shipping_fee' => '0.00',
                'total' => $subtotal,
                'status' => 'pending',
                'payment_status' => 'pending',
                'delivery_status' => 'pending',
                'expires_at' => now()->addMinutes(30),
            ]);

            foreach ($orderItems as $item) {
                $order->items()->create($item);

                $this->inventoryService->adjustStock(
                    productId: $item['product_id'],
                    quantityChange: -$item['quantity'],
                    reason: 'Stock reserved for order '.$order->order_number,
                    type: 'order_reservation',
                    referenceType: 'order',
                    referenceId: $order->id
                );
            }

            return $order->load('items');
        }, 3);
    }
}
