<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Services\InventoryService;
use App\Services\PaymentConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PaymentConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    private string $transactionId = 'PAY-CONFIRM-001';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.sslcommerz.sandbox', true);
        config()->set('services.sslcommerz.store_id', 'test_store');
        config()->set('services.sslcommerz.store_password', 'test_password');

        Http::preventStrayRequests();

        $product = Product::create([
            'name' => 'Wireless Keyboard',
            'sku' => 'KEY-PAY-CONFIRM-001',
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

        $this->order->payments()->create([
            'gateway' => 'sslcommerz',
            'transaction_id' => $this->transactionId,
            'amount' => $this->order->total,
            'currency' => 'BDT',
            'status' => 'pending',
        ]);
    }

    private function fakeVerification(
        string $status = 'VALID',
        string $amount = '3000.00',
        string $currency = 'BDT'
    ): void {
        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response([
                'APIConnect' => 'DONE',
                'no_of_trans_found' => 1,
                'element' => [
                    [
                        'risk_level' => '0',
                        'status' => $status,
                        'tran_id' => $this->transactionId,
                        'val_id' => 'SSL-VALIDATION-001',
                        'amount' => $amount,
                        'currency' => $currency,
                    ],
                ],
            ]),
        ]);
    }

    public function test_valid_payment_confirms_order(): void
    {
        $this->fakeVerification();

        $payment = app(PaymentConfirmationService::class)
            ->confirm($this->transactionId);

        $this->assertSame('paid', $payment->status);

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $this->assertNotNull($payment->verified_at);
        $this->assertNotNull($payment->paid_at);

        Http::assertSentCount(1);
    }

    public function test_duplicate_confirmation_is_idempotent(): void
    {
        $this->fakeVerification();

        $service = app(PaymentConfirmationService::class);

        $service->confirm($this->transactionId);
        $service->confirm($this->transactionId);

        $this->assertSame(
            1,
            $this->order->payments()->where('status', 'paid')->count()
        );

        Http::assertSentCount(1);
    }

    public function test_invalid_gateway_status_is_rejected(): void
    {
        $this->fakeVerification(status: 'FAILED');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Gateway did not confirm the transaction.'
        );

        app(PaymentConfirmationService::class)
            ->confirm($this->transactionId);
    }

    public function test_payment_amount_mismatch_is_rejected(): void
    {
        $this->fakeVerification(amount: '2500.00');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Payment amount or currency mismatch.'
        );

        app(PaymentConfirmationService::class)
            ->confirm($this->transactionId);
    }

    public function test_cancelled_order_cannot_be_confirmed(): void
    {
        $this->order->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $this->fakeVerification();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Order is not eligible for payment confirmation.'
        );

        app(PaymentConfirmationService::class)
            ->confirm($this->transactionId);
    }

    
    public function test_expired_order_cannot_be_confirmed(): void
    {
        $this->order->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this->fakeVerification();

        try {
            app(PaymentConfirmationService::class)
                ->confirm($this->transactionId);

            $this->fail('Expected expired order confirmation to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Order is not eligible for payment confirmation.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $this->assertDatabaseHas('payments', [
            'transaction_id' => $this->transactionId,
            'status' => 'pending',
        ]);
    }


    
    public function test_failed_payment_attempt_cannot_be_confirmed(): void
    {
        $this->order->payments()
            ->where('transaction_id', $this->transactionId)
            ->update(['status' => 'failed']);

        $this->fakeVerification();

        try {
            app(PaymentConfirmationService::class)
                ->confirm($this->transactionId);

            $this->fail('Expected failed payment confirmation to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Payment attempt is not eligible for confirmation.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $this->assertDatabaseHas('payments', [
            'transaction_id' => $this->transactionId,
            'status' => 'failed',
        ]);
    }

    
    public function test_risky_payment_cannot_be_confirmed(): void
    {
        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response([
                'APIConnect' => 'DONE',
                'no_of_trans_found' => 1,
                'element' => [
                    [
                        'status' => 'VALID',
                        'tran_id' => $this->transactionId,
                        'val_id' => 'SSL-RISK-001',
                        'amount' => '3000.00',
                        'currency' => 'BDT',
                        'risk_level' => '1',
                    ],
                ],
            ]),
        ]);

        try {
            app(PaymentConfirmationService::class)
                ->confirm($this->transactionId);

            $this->fail('Expected risky payment to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Gateway flagged the payment for risk review.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $this->assertDatabaseHas('payments', [
            'transaction_id' => $this->transactionId,
            'status' => 'pending',
        ]);
    }


    public function test_missing_risk_information_is_rejected(): void
    {
        // $this->fakeVerification();

        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response([
                'APIConnect' => 'DONE',
                'no_of_trans_found' => 1,
                'element' => [
                    [
                        'status' => 'VALID',
                        'tran_id' => $this->transactionId,
                        'val_id' => 'SSL-MISSING-RISK-001',
                        'amount' => '3000.00',
                        'currency' => 'BDT',
                    ],
                ],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Gateway risk information is missing.'
        );

        app(PaymentConfirmationService::class)
            ->confirm($this->transactionId);
    }
    
    public function test_duplicate_gateway_reference_is_rejected(): void
    {
        $this->order->payments()->create([
            'gateway' => 'sslcommerz',
            'transaction_id' => 'PAY-PREVIOUS-001',
            'gateway_payment_id' => 'SSL-VALIDATION-001',
            'amount' => $this->order->total,
            'currency' => 'BDT',
            'status' => 'failed',
        ]);

        $this->fakeVerification();

        try {
            app(PaymentConfirmationService::class)
                ->confirm($this->transactionId);

            $this->fail('Expected duplicate gateway reference to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Gateway payment reference is already associated with another payment.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $this->assertDatabaseHas('payments', [
            'transaction_id' => $this->transactionId,
            'status' => 'pending',
        ]);

        $this->assertDatabaseCount('payments', 2);
    }


}
