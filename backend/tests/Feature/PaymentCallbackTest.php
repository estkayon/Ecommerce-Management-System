<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentCallbackTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;
    private string $transactionId = 'PAY-CALLBACK-001';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.sslcommerz.sandbox', true);
        config()->set('services.sslcommerz.store_id', 'test_store');
        config()->set('services.sslcommerz.store_password', 'test_password');

        Http::preventStrayRequests();

        $product = Product::create([
            'name' => 'Wireless Mouse',
            'sku' => 'MOUSE-CALLBACK-001',
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
            'customer_name' => 'Callback Test Customer',
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

        $this->order->payments()->create([
            'gateway' => 'sslcommerz',
            'transaction_id' => $this->transactionId,
            'amount' => $this->order->total,
            'currency' => 'BDT',
            'status' => 'pending',
        ]);
    }

    private function fakeVerifiedPayment(): void
    {
        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response([
                'APIConnect' => 'DONE',
                'no_of_trans_found' => 1,
                'element' => [
                    [
                        'status' => 'VALID',
                        'tran_id' => $this->transactionId,
                        'val_id' => 'SSL-CALLBACK-001',
                        'amount' => '3000.00',
                        'currency' => 'BDT',
                        'risk_level' => '0',
                    ],
                ],
            ]),
        ]);
    }

    public function test_success_callback_confirms_verified_payment(): void
    {
        $this->fakeVerifiedPayment();

        $this->postJson('/api/payments/sslcommerz/success', [
            'tran_id' => $this->transactionId,
        ])->assertOk()
            ->assertJsonPath('status', 'paid');

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $this->assertDatabaseHas('payments', [
            'transaction_id' => $this->transactionId,
            'status' => 'paid',
        ]);

        Http::assertSentCount(1);
    }

    public function test_ipn_callback_confirms_verified_payment(): void
    {
        $this->fakeVerifiedPayment();

        $this->postJson('/api/payments/sslcommerz/ipn', [
            'tran_id' => $this->transactionId,
        ])->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'payment_status' => 'paid',
        ]);
    }

    public function test_invalid_transaction_is_rejected(): void
    {
        Http::fake();

        $this->postJson('/api/payments/sslcommerz/success', [
            'tran_id' => 'UNKNOWN-TRANSACTION',
        ])->assertStatus(422);

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'payment_status' => 'pending',
        ]);

        Http::assertNothingSent();
    }

    public function test_duplicate_callbacks_do_not_duplicate_confirmation(): void
    {
        $this->fakeVerifiedPayment();

        $this->postJson('/api/payments/sslcommerz/success', [
            'tran_id' => $this->transactionId,
        ])->assertOk();

        $this->postJson('/api/payments/sslcommerz/ipn', [
            'tran_id' => $this->transactionId,
        ])->assertOk();

        $this->assertSame(
            1,
            $this->order->payments()->where('status', 'paid')->count()
        );

        Http::assertSentCount(1);
    }

    public function test_missing_transaction_id_is_rejected(): void
    {
        $this->postJson('/api/payments/sslcommerz/success', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tran_id']);
    }

    public function test_failure_callback_does_not_confirm_payment(): void
    {
        $this->postJson('/api/payments/sslcommerz/fail', [
            'tran_id' => $this->transactionId,
        ])->assertStatus(202);

        $this->assertDatabaseHas('payments', [
            'transaction_id' => $this->transactionId,
            'status' => 'pending',
        ]);
    }
}
