<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderExpirationTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::create([
            'name' => 'Gaming Keyboard',
            'sku' => 'KEYBOARD-EXP-001',
            'price' => '2500.00',
            'is_active' => true,
        ]);

        $this->product->inventory()->create();

        app(InventoryService::class)->adjustStock(
            productId: $this->product->id,
            quantityChange: 10,
            reason: 'Initial test stock'
        );

        $response = $this->postJson('/api/checkout', [
            'customer_name' => 'Expiry Test Customer',
            'customer_phone' => '01712345678',
            'shipping_address' => 'Dhaka, Bangladesh',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                ],
            ],
        ])->assertCreated();

        $this->order = Order::findOrFail($response->json('data.id'));
    }

    public function test_checkout_sets_thirty_minute_expiry(): void
    {
        $this->assertNotNull($this->order->expires_at);

        // Compare both timestamps in UTC to avoid timezone differences.
        $createdAt = $this->order->created_at->copy()->utc();
        $expiresAt = $this->order->expires_at->copy()->utc();

        $this->assertEqualsWithDelta(
            1800,
            $createdAt->diffInSeconds($expiresAt),
            2
        );
    }

    public function test_expired_unpaid_order_restores_stock(): void
    {
        $this->order->update(['expires_at' => now()->subMinute()]);

        $this->artisan('orders:expire')
            ->assertSuccessful();

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('inventories', [
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);
    }

    public function test_unexpired_order_remains_pending(): void
    {
        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('inventories', [
            'product_id' => $this->product->id,
            'quantity' => 8,
        ]);
    }

    public function test_paid_order_is_not_expired(): void
    {
        $this->order->update([
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $this->assertDatabaseHas('inventories', [
            'product_id' => $this->product->id,
            'quantity' => 8,
        ]);
    }

    public function test_repeated_expiration_does_not_restore_stock_twice(): void
    {
        $this->order->update(['expires_at' => now()->subMinute()]);

        $this->artisan('orders:expire')->assertSuccessful();
        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertDatabaseHas('inventories', [
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        // Initial stock, order reservation, and one restoration.
        $this->assertDatabaseCount('inventory_movements', 3);
    }
}
