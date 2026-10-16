<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        Sanctum::actingAs($admin, ['admin']);

        $this->product = Product::create([
            'name' => 'Gaming Keyboard',
            'sku' => 'KEYBOARD-001',
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
            'customer_name' => 'Test Customer',
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

    public function test_admin_can_cancel_pending_order_and_restore_stock(): void
    {
        $this->postJson("/api/admin/orders/{$this->order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('inventories', [
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'quantity_change' => 2,
            'type' => 'order_cancellation',
            'reference_id' => $this->order->id,
        ]);
    }

    public function test_duplicate_cancellation_does_not_restore_stock_twice(): void
    {
        $url = "/api/admin/orders/{$this->order->id}/cancel";

        $this->postJson($url)->assertOk();
        $this->postJson($url)->assertOk();

        $this->assertDatabaseHas('inventories', [
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        $this->assertDatabaseCount('inventory_movements', 3);
    }

    public function test_paid_order_cannot_be_cancelled(): void
    {
        $this->order->update([
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->postJson("/api/admin/orders/{$this->order->id}/cancel")
            ->assertUnprocessable();

        $this->assertDatabaseHas('inventories', [
            'product_id' => $this->product->id,
            'quantity' => 8,
        ]);
    }

    public function test_non_admin_cannot_cancel_order(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/admin/orders/{$this->order->id}/cancel")
            ->assertForbidden();

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => 'pending',
        ]);
    }

    public function test_cancellation_failure_rolls_back_order_status_and_stock(): void
    {
        // Force inventory restoration to fail inside the transaction.
        \Illuminate\Support\Facades\DB::statement("
            CREATE OR REPLACE FUNCTION reject_cancellation_stock_update()
            RETURNS trigger AS \$\$
            BEGIN
                IF NEW.quantity > OLD.quantity THEN
                    RAISE EXCEPTION 'Simulated restoration failure';
                END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql
        ");

        \Illuminate\Support\Facades\DB::statement("
            CREATE TRIGGER reject_cancellation_stock_update_trigger
            BEFORE UPDATE ON inventories
            FOR EACH ROW
            EXECUTE FUNCTION reject_cancellation_stock_update()
        ");

        $this->postJson("/api/admin/orders/{$this->order->id}/cancel")
            ->assertInternalServerError();

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('inventories', [
            'product_id' => $this->product->id,
            'quantity' => 8,
        ]);

        $this->assertDatabaseCount('inventory_movements', 2);
    }

    
    public function test_order_with_active_payment_cannot_be_cancelled(): void
    {
        $product = \App\Models\Product::create([
            'name' => 'Payment Protected Product',
            'sku' => 'PAY-PROTECT-001',
            'price' => '1000.00',
            'is_active' => true,
        ]);

        $product->inventory()->create();

        app(\App\Services\InventoryService::class)->adjustStock(
            productId: $product->id,
            quantityChange: 10,
            reason: 'Initial test stock'
        );

        $response = $this->postJson('/api/checkout', [
            'customer_name' => 'Payment Test Customer',
            'customer_email' => 'customer@example.com',
            'customer_phone' => '01712345678',
            'shipping_address' => 'Dhaka, Bangladesh',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ])->assertCreated();

        $order = \App\Models\Order::findOrFail($response->json('data.id'));

        $order->payments()->create([
            'gateway' => 'sslcommerz',
            'transaction_id' => 'PAY-ACTIVE-TEST-001',
            'amount' => $order->total,
            'currency' => 'BDT',
            'status' => 'pending',
        ]);

        try {
            app(\App\Services\OrderCancellationService::class)
                ->cancel($order->id);

            $this->fail('Expected cancellation to be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('order', $exception->errors());
        }

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('inventories', [
            'product_id' => $product->id,
            'quantity' => 8,
        ]);
    }


}
