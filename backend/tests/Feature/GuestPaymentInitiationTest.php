<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GuestPaymentInitiationTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.sslcommerz.sandbox', true);
        config()->set('services.sslcommerz.store_id', 'test_store');
        config()->set('services.sslcommerz.store_password', 'test_password');

        Http::preventStrayRequests();

        $product = Product::create([
            'name' => 'Wireless Mouse',
            'sku' => 'GUEST-PAY-001',
            'price' => '1500.00',
            'is_active' => true,
        ]);

        $product->inventory()->create();

        app(InventoryService::class)->adjustStock(
            productId: $product->id,
            quantityChange: 10,
            reason: 'Initial test stock'
        );

        $response = $this->postJson('/api/checkout', [
            'customer_name' => 'Guest Payment Customer',
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

        $this->order = Order::findOrFail($response->json('data.id'));
        $this->token = $response->json('payment_access_token');
    }

    private function endpoint(): string
    {
        return "/api/orders/{$this->order->id}/payments/initiate";
    }

    private function fakeGateway(): void
    {
        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response([
                'status' => 'SUCCESS',
                'sessionkey' => 'guest-test-session',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/test',
            ]),
        ]);
    }

    public function test_valid_token_initiates_payment(): void
    {
        $this->fakeGateway();

        $this->postJson($this->endpoint(), [
            'payment_access_token' => $this->token,
        ])->assertCreated()
            ->assertJsonPath(
                'data.gateway_url',
                'https://sandbox.sslcommerz.com/pay/test'
            );

        $this->assertDatabaseHas('payments', [
            'order_id' => $this->order->id,
            'status' => 'pending',
            'gateway' => 'sslcommerz',
        ]);

        Http::assertSentCount(1);
    }

    public function test_invalid_token_is_rejected(): void
    {
        Http::fake();

        $this->postJson($this->endpoint(), [
            'payment_access_token' => str_repeat('x', 64),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_access_token']);

        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }

    public function test_missing_token_is_rejected(): void
    {
        Http::fake();

        $this->postJson($this->endpoint(), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_access_token']);

        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }

    public function test_duplicate_initiation_is_rejected(): void
    {
        $this->fakeGateway();

        $this->postJson($this->endpoint(), [
            'payment_access_token' => $this->token,
        ])->assertCreated();

        $this->postJson($this->endpoint(), [
            'payment_access_token' => $this->token,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('payments', 1);
        Http::assertSentCount(1);
    }

    public function test_expired_order_cannot_initiate_payment(): void
    {
        $this->order->update([
            'expires_at' => now()->subMinute(),
        ]);

        Http::fake();

        $this->postJson($this->endpoint(), [
            'payment_access_token' => $this->token,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }
}
