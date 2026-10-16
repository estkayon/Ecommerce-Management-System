<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\InventoryService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PaymentInitiationTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    private array $callbackUrls = [
        'success' => 'https://example.com/payment/success',
        'fail' => 'https://example.com/payment/fail',
        'cancel' => 'https://example.com/payment/cancel',
        'ipn' => 'https://example.com/payment/ipn',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.sslcommerz.sandbox', true);
        config()->set('services.sslcommerz.store_id', 'test_store');
        config()->set('services.sslcommerz.store_password', 'test_password');

        Http::preventStrayRequests();

        $product = Product::create([
            'name' => 'Gaming Mouse',
            'sku' => 'MOUSE-PAY-001',
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

        $this->order = Order::findOrFail($response->json('data.id'));
    }

    private function initiatePayment(): array
    {
        return app(PaymentService::class)->initiate(
            $this->order,
            $this->callbackUrls
        );
    }

    public function test_successful_payment_initiation(): void
    {
        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response([
                'status' => 'SUCCESS',
                'sessionkey' => 'test-session-123',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/test',
            ]),
        ]);

        $result = $this->initiatePayment();

        $this->assertNotEmpty($result['transaction_id']);
        $this->assertNotEmpty($result['gateway_url']);

        $this->assertDatabaseHas('payments', [
            'id' => $result['payment_id'],
            'order_id' => $this->order->id,
            'gateway' => 'sslcommerz',
            'status' => 'pending',
            'amount' => '3000.00',
        ]);

        Http::assertSentCount(1);
    }

    public function test_duplicate_active_payment_is_rejected(): void
    {
        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response([
                'status' => 'SUCCESS',
                'sessionkey' => 'test-session-123',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/test',
            ]),
        ]);

        $this->initiatePayment();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'An active payment attempt already exists.'
        );

        $this->initiatePayment();
    }

    public function test_expired_order_cannot_initiate_payment(): void
    {
        $this->order->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Order is not eligible for payment.'
        );

        $this->initiatePayment();
    }

    public function test_gateway_failure_marks_payment_failed(): void
    {
        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response([
                'status' => 'FAILED',
                'failedreason' => 'Invalid store credentials',
            ]),
        ]);

        try {
            $this->initiatePayment();
            $this->fail('Expected payment initiation to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Unable to initiate payment.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('payments', [
            'order_id' => $this->order->id,
            'status' => 'failed',
        ]);
    }

    public function test_failed_payment_can_be_retried(): void
    {
        Http::fakeSequence()
            ->push(['status' => 'FAILED'])
            ->push([
                'status' => 'SUCCESS',
                'sessionkey' => 'retry-session',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/retry',
            ]);

        try {
            $this->initiatePayment();
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Unable to initiate payment.',
                $exception->getMessage()
            );
        }

        $result = $this->initiatePayment();

        $this->assertNotEmpty($result['gateway_url']);
        $this->assertSame(2, Payment::count());

        $this->assertSame(
            1,
            Payment::where('status', 'failed')->count()
        );

        $this->assertSame(
            1,
            Payment::where('status', 'pending')->count()
        );

        $this->assertSame(
            2,
            Payment::distinct()->count('transaction_id')
        );
    }
}
