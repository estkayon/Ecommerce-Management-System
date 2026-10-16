<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.sslcommerz.sandbox', true);
        config()->set('services.sslcommerz.store_id', 'test_store');
        config()->set('services.sslcommerz.store_password', 'test_password');

        Http::preventStrayRequests();

        $order = Order::create([
            'order_number' => 'ORD-RECON-001',
            'customer_name' => 'Test Customer',
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

        $this->payment = $order->payments()->create([
            'gateway' => 'sslcommerz',
            'transaction_id' => 'PAY-RECON-001',
            'amount' => '2500.00',
            'currency' => 'BDT',
            'status' => 'pending',
        ]);
    }

    private function fakeUnresolvedPayment(): void
    {
        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response([
                'APIConnect' => 'DONE',
                'no_of_trans_found' => 0,
                'element' => [],
            ]),
        ]);
    }

    public function test_pending_payment_is_marked_for_reconciliation(): void
    {
        $this->fakeUnresolvedPayment();

        $payment = app(PaymentReconciliationService::class)
            ->reconcile($this->payment->id);

        $this->assertSame('pending', $payment->status);
        $this->assertNotNull($payment->last_checked_at);
        $this->assertNotNull($payment->reconciliation_required_at);

        Http::assertSentCount(1);
    }

    public function test_gateway_failure_preserves_payment_status(): void
    {
        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response(
                ['message' => 'Gateway unavailable'],
                503
            ),
        ]);

        $payment = app(PaymentReconciliationService::class)
            ->reconcile($this->payment->id);

        $this->assertSame('pending', $payment->status);
        $this->assertNotNull($payment->reconciliation_required_at);
    }

    public function test_paid_payment_is_not_modified(): void
    {
        $this->payment->update([
            'status' => 'paid',
            'verified_at' => now(),
            'paid_at' => now(),
        ]);

        Http::fake();

        $payment = app(PaymentReconciliationService::class)
            ->reconcile($this->payment->id);

        $this->assertSame('paid', $payment->status);
        $this->assertNull($payment->reconciliation_required_at);

        Http::assertNothingSent();
    }

    public function test_failed_payment_is_not_reprocessed(): void
    {
        $this->payment->update([
            'status' => 'failed',
        ]);

        Http::fake();

        $payment = app(PaymentReconciliationService::class)
            ->reconcile($this->payment->id);

        $this->assertSame('failed', $payment->status);
        $this->assertNull($payment->reconciliation_required_at);

        Http::assertNothingSent();
    }

    public function test_repeated_reconciliation_does_not_change_payment_status(): void
    {
        $this->fakeUnresolvedPayment();

        $service = app(PaymentReconciliationService::class);

        $service->reconcile($this->payment->id);
        $service->reconcile($this->payment->id);

        $this->assertDatabaseHas('payments', [
            'id' => $this->payment->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $this->payment->order_id,
            'payment_status' => 'pending',
        ]);

        Http::assertSentCount(2);
    }

    public function test_reconciliation_command_respects_five_minute_retry_interval(): void
    {
        $this->fakeUnresolvedPayment();

        $this->travel(10)->minutes();

        $this->artisan('payments:reconcile')
            ->assertSuccessful();

        $this->assertNotNull(
            $this->payment->fresh()->last_checked_at
        );

        Http::assertSentCount(1);

        $this->artisan('payments:reconcile')
            ->assertSuccessful();

        Http::assertSentCount(1);

        $this->travel(6)->minutes();

        $this->artisan('payments:reconcile')
            ->assertSuccessful();

        Http::assertSentCount(2);

        $this->assertSame(
            'pending',
            $this->payment->fresh()->status
        );
    }

    public function test_expired_order_with_active_payment_is_preserved_for_reconciliation(): void
    {
        $this->fakeUnresolvedPayment();

        $this->travel(10)->minutes();

        // The current expiration command reports blocked cancellations as failures.
        $this->artisan('orders:expire')
            ->assertExitCode(1);

        $this->assertDatabaseHas('orders', [
            'id' => $this->payment->order_id,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $this->artisan('payments:reconcile')
            ->assertSuccessful();

        $payment = $this->payment->fresh();

        $this->assertSame('pending', $payment->status);
        $this->assertNotNull($payment->reconciliation_required_at);
        $this->assertNotNull($payment->last_checked_at);

        Http::assertSentCount(1);
    }

    public function test_recent_payment_is_not_reconciled_early(): void
    {
        $this->fakeUnresolvedPayment();

        $this->artisan('payments:reconcile')
            ->assertSuccessful();

        $this->assertNull(
            $this->payment->fresh()->last_checked_at
        );

        Http::assertNothingSent();
    }
}
