<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\PaymentResolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_payment_cannot_be_resolved_as_failed(): void
    {
        config()->set('services.sslcommerz.sandbox', true);
        config()->set('services.sslcommerz.store_id', 'test_store');
        config()->set('services.sslcommerz.store_password', 'test_password');

        Http::preventStrayRequests();

        $order = Order::create([
            'order_number' => 'ORD-RESOLVE-001',
            'customer_name' => 'Resolution Customer',
            'customer_email' => 'customer@example.com',
            'customer_phone' => '01712345678',
            'shipping_address' => 'Dhaka, Bangladesh',
            'subtotal' => '2500.00',
            'shipping_fee' => '0.00',
            'total' => '2500.00',
            'status' => 'pending',
            'payment_status' => 'pending',
            'delivery_status' => 'pending',
            'expires_at' => now()->subMinute(),
        ]);

        $payment = $order->payments()->create([
            'gateway' => 'sslcommerz',
            'transaction_id' => 'PAY-RESOLVE-001',
            'amount' => '2500.00',
            'currency' => 'BDT',
            'status' => 'pending',
            'metadata' => [
                'manual_review' => [
                    'decision' => 'confirmed_unsuccessful',
                ],
            ],
        ]);

        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response([
                'APIConnect' => 'DONE',
                'no_of_trans_found' => 0,
                'element' => [],
            ]),
        ]);

        try {
            app(PaymentResolutionService::class)
                ->resolveUnsuccessful(
                    $payment->id,
                    1,
                    'Gateway investigation completed.'
                );

            $this->fail('Expected resolution to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'payment',
                $exception->errors()
            );
        }

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        Http::assertSentCount(1);
    }

    
public function test_gateway_confirmed_failure_can_be_manually_resolved(): void
{
    config()->set('services.sslcommerz.sandbox', true);
    config()->set('services.sslcommerz.store_id', 'test_store');
    config()->set('services.sslcommerz.store_password', 'test_password');

    Http::preventStrayRequests();

    $order = Order::create([
        'order_number' => 'ORD-RESOLVE-002',
        'customer_name' => 'Resolution Customer',
        'customer_email' => 'customer@example.com',
        'customer_phone' => '01712345678',
        'shipping_address' => 'Dhaka, Bangladesh',
        'subtotal' => '2500.00',
        'shipping_fee' => '0.00',
        'total' => '2500.00',
        'status' => 'pending',
        'payment_status' => 'pending',
        'delivery_status' => 'pending',
        'expires_at' => now()->subMinute(),
    ]);

    $payment = $order->payments()->create([
        'gateway' => 'sslcommerz',
        'transaction_id' => 'PAY-RESOLVE-002',
        'amount' => '2500.00',
        'currency' => 'BDT',
        'status' => 'pending',
        'metadata' => [
            'manual_review' => [
                'decision' => 'confirmed_unsuccessful',
            ],
        ],
    ]);

    Http::fake([
        'sandbox.sslcommerz.com/*' => Http::response([
            'APIConnect' => 'DONE',
            'no_of_trans_found' => 1,
            'element' => [
                [
                    'tran_id' => 'PAY-RESOLVE-002',
                    'status' => 'FAILED',
                ],
            ],
        ]),
    ]);

    $resolved = app(PaymentResolutionService::class)
        ->resolveUnsuccessful(
            $payment->id,
            1,
            'Gateway records show an unsuccessful payment attempt.'
        );

    $this->assertSame('failed', $resolved->status);

    $this->assertSame(
        'confirmed_unsuccessful',
        $resolved->metadata['resolution']['type']
    );

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'pending',
        'payment_status' => 'pending',
    ]);

    Http::assertSentCount(1);
}

}
