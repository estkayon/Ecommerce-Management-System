<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::create([
            'name' => 'Gaming Keyboard',
            'sku' => 'KEYBOARD-001',
            'price' => '2500.00',
            'is_active' => true,
        ]);

        // Initialize stock through the InventoryService to preserve movement history.
        $this->product->inventory()->create();

        app(\App\Services\InventoryService::class)->adjustStock(
            productId: $this->product->id,
            quantityChange: 10,
            reason: 'Initial test stock'
        );
    }

    private function checkoutData(array $overrides = []): array
    {
        return array_replace([
            'customer_name' => 'Test Customer',
            'customer_phone' => '01712345678',
            'customer_email' => 'test@example.com',
            'shipping_address' => 'Dhaka, Bangladesh',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                ],
            ],
        ], $overrides);
    }

    public function test_customer_can_place_order(): void
    {
        $this->postJson('/api/checkout', $this->checkoutData())
            ->assertCreated()
            ->assertJsonPath('data.total', '5000.00')
            ->assertJsonPath('data.payment_status', 'pending');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseHas('inventories', [
            'product_id' => $this->product->id,
            'quantity' => 8,
        ]);
    }

    public function test_backend_calculates_actual_product_price(): void
    {
        $data = $this->checkoutData();
        $data['items'][0]['unit_price'] = 1;
        $data['items'][0]['total_price'] = 2;

        $this->postJson('/api/checkout', $data)
            ->assertCreated()
            ->assertJsonPath('data.total', '5000.00')
            ->assertJsonPath('data.items.0.unit_price', '2500.00');
    }

    public function test_insufficient_stock_rejects_checkout(): void
    {
        $data = $this->checkoutData();
        $data['items'][0]['quantity'] = 20;

        $this->postJson('/api/checkout', $data)
            ->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_movements', 1);

        $this->assertDatabaseHas('inventories', [
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);
    }

    public function test_invalid_checkout_data_is_rejected(): void
    {
        $this->postJson('/api/checkout', $this->checkoutData([
            'customer_name' => '',
            'customer_phone' => '',
            'items' => [],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'customer_name',
                'customer_phone',
                'items',
            ]);
    }

    public function test_duplicate_product_items_are_rejected(): void
    {
        $data = $this->checkoutData();
        $data['items'][] = $data['items'][0];

        $this->postJson('/api/checkout', $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.product_id');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_inactive_product_cannot_be_ordered(): void
    {
        $this->product->update(['is_active' => false]);

        $this->postJson('/api/checkout', $this->checkoutData())
            ->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_successful_checkout_records_inventory_movement(): void
    {
        $this->postJson('/api/checkout', $this->checkoutData())
            ->assertCreated();

        $order = Order::firstOrFail();

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'quantity_before' => 10,
            'quantity_change' => -2,
            'quantity_after' => 8,
            'type' => 'order_reservation',
            'reference_id' => $order->id,
        ]);
    }

    public function test_failed_multi_product_checkout_rolls_back(): void
    {
        $secondProduct = Product::create([
            'name' => 'Wireless Mouse',
            'sku' => 'MOUSE-TEST-001',
            'price' => '850.00',
            'is_active' => true,
        ]);

        $secondProduct->inventory()->create(['quantity' => 0]);

        $data = $this->checkoutData([
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
                ['product_id' => $secondProduct->id, 'quantity' => 1],
            ],
        ]);

        $this->postJson('/api/checkout', $data)
            ->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('inventory_movements', 1);

        $this->assertDatabaseHas('inventories', [
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);
    }

    
    public function test_checkout_rolls_back_when_stock_update_fails(): void
    {
        $secondProduct = Product::create([
            'name' => 'Test Mouse',
            'sku' => 'ROLLBACK-001',
            'price' => '500.00',
            'is_active' => true,
        ]);

        $secondProduct->inventory()->create();

        app(\App\Services\InventoryService::class)->adjustStock(
            productId: $secondProduct->id,
            quantityChange: 5,
            reason: 'Initial test stock'
        );

        // Force a database constraint violation during the second stock adjustment.
        \Illuminate\Support\Facades\DB::statement("
            CREATE OR REPLACE FUNCTION reject_test_inventory_update()
            RETURNS trigger AS \$\$
            BEGIN
                IF NEW.product_id = {$secondProduct->id}
                AND NEW.quantity < OLD.quantity THEN
                    RAISE EXCEPTION 'Simulated inventory failure';
                END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql
        ");

        \Illuminate\Support\Facades\DB::statement("
            CREATE TRIGGER reject_test_inventory_update_trigger
            BEFORE UPDATE ON inventories
            FOR EACH ROW
            EXECUTE FUNCTION reject_test_inventory_update()
        ");

        $data = $this->checkoutData([
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
                ['product_id' => $secondProduct->id, 'quantity' => 1],
            ],
        ]);

        try {
            $this->postJson('/api/checkout', $data);
        } catch (\Throwable $exception) {
            // The simulated database failure should roll back the checkout.
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);

        $this->assertDatabaseHas('inventories', [
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        $this->assertDatabaseHas('inventories', [
            'product_id' => $secondProduct->id,
            'quantity' => 5,
        ]);

        $this->assertDatabaseCount('inventory_movements', 2);
    }

    
    public function test_checkout_generates_secure_payment_access_token(): void
    {
        $response = $this->postJson('/api/checkout', [
            'customer_name' => 'Token Test Customer',
            'customer_email' => 'customer@example.com',
            'customer_phone' => '01712345678',
            'shipping_address' => 'Dhaka, Bangladesh',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                ],
            ],
        ])->assertCreated();

        $token = $response->json('payment_access_token');
        $orderId = $response->json('data.id');

        $this->assertIsString($token);
        $this->assertSame(64, strlen($token));

        $order = \App\Models\Order::findOrFail($orderId);

        $this->assertSame(
            hash('sha256', $token),
            $order->payment_access_token_hash
        );

        $response->assertJsonMissingPath(
            'data.payment_access_token_hash'
        );

        $response->assertJsonMissingPath(
            'data.paymentAccessToken'
        );
    }


}
