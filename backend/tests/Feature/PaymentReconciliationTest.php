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
        $this->fakeVerification([
            'APIConnect' => 'DONE',
            'no_of_trans_found' => 0,
            'element' => [],
        ]);
    }

    private function makeOrderActive(): void
    {
        $this->payment->order->update([
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    private function successfulVerification(array $overrides = []): array
    {
        return [
            'APIConnect' => 'DONE',
            'no_of_trans_found' => 1,
            'element' => [
                array_merge([
                    'status' => 'VALID',
                    'tran_id' => $this->payment->transaction_id,
                    'val_id' => 'SSL-RECOVER-001',
                    'amount' => '2500.00',
                    'currency' => 'BDT',
                    'risk_level' => '0',
                ], $overrides),
            ],
        ];
    }

    private function fakeVerification(array $verification): void
    {
        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response($verification),
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

        Http::assertSentCount(1);
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

        $this->artisan('orders:expire')
            ->expectsOutput('Processed: 0, Skipped: 1, Failed: 0')
            ->assertSuccessful();

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

    public function test_successful_payment_is_recovered_when_callback_is_missing(): void
    {
        $this->makeOrderActive();
        $this->fakeVerification($this->successfulVerification());

        $payment = app(PaymentReconciliationService::class)
            ->reconcile($this->payment->id);

        $this->assertSame('paid', $payment->status);

        $this->assertDatabaseHas('orders', [
            'id' => $payment->order_id,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $this->assertNotNull($payment->verified_at);
        $this->assertNotNull($payment->paid_at);

        Http::assertSentCount(1);
    }

    public function test_expired_order_with_successful_gateway_payment_requires_review(): void
    {
        $this->fakeVerification($this->successfulVerification());

        $payment = app(PaymentReconciliationService::class)
            ->reconcile($this->payment->id);

        $this->assertSame('pending', $payment->status);
        $this->assertNotNull($payment->last_checked_at);
        $this->assertNotNull($payment->reconciliation_required_at);

        $this->assertSame(
            'late_successful_payment',
            $payment->metadata['reconciliation_reason'] ?? null
        );

        $this->assertNotEmpty(
            $payment->metadata['review_detected_at'] ?? null
        );

        $this->assertDatabaseHas('orders', [
            'id' => $payment->order_id,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        Http::assertSentCount(1);
    }

    public function test_reconciliation_command_processes_all_active_payment_statuses(): void
    {
        $this->fakeUnresolvedPayment();

        $order = $this->payment->order;

        $initiatingPayment = $order->payments()->create([
            'gateway' => 'sslcommerz',
            'transaction_id' => 'PAY-RECON-INITIATING',
            'amount' => '2500.00',
            'currency' => 'BDT',
            'status' => 'initiating',
        ]);

        $processingPayment = $order->payments()->create([
            'gateway' => 'sslcommerz',
            'transaction_id' => 'PAY-RECON-PROCESSING',
            'amount' => '2500.00',
            'currency' => 'BDT',
            'status' => 'processing',
        ]);

        $this->travel(10)->minutes();

        $this->artisan('payments:reconcile')
            ->expectsOutput('Processed: 3, Failed: 0')
            ->assertSuccessful();

        foreach ([
            $this->payment,
            $initiatingPayment,
            $processingPayment,
        ] as $payment) {
            $this->assertNotNull(
                $payment->fresh()->last_checked_at
            );

            $this->assertSame(
                $payment->status,
                $payment->fresh()->status
            );
        }

        Http::assertSentCount(3);
    }

    public function test_gateway_http_failure_keeps_payment_pending_for_retry(): void
    {
        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response(
                ['message' => 'Service temporarily unavailable'],
                503
            ),
        ]);

        $payment = app(PaymentReconciliationService::class)
            ->reconcile($this->payment->id);

        $this->assertSame('pending', $payment->status);
        $this->assertNotNull($payment->last_checked_at);
        $this->assertNotNull($payment->reconciliation_required_at);

        $this->assertDatabaseHas('orders', [
            'id' => $payment->order_id,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        Http::assertSentCount(1);
    }

    public function test_unknown_gateway_transaction_remains_pending(): void
    {
        $this->fakeUnresolvedPayment();

        $payment = app(PaymentReconciliationService::class)
            ->reconcile($this->payment->id);

        $this->assertSame('pending', $payment->status);
        $this->assertNotNull($payment->reconciliation_required_at);

        $this->assertDatabaseHas('orders', [
            'id' => $payment->order_id,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        Http::assertSentCount(1);
    }

    public function test_successful_initiating_payment_can_be_recovered(): void
    {
        $this->payment->update([
            'status' => 'initiating',
        ]);

        $this->makeOrderActive();
        $this->fakeVerification($this->successfulVerification());

        $payment = app(PaymentReconciliationService::class)
            ->reconcile($this->payment->id);

        $this->assertSame('paid', $payment->status);

        $this->assertDatabaseHas('orders', [
            'id' => $payment->order_id,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        Http::assertSentCount(1);
    }

    public function test_successful_processing_payment_can_be_recovered(): void
    {
        $this->payment->update([
            'status' => 'processing',
        ]);

        $this->makeOrderActive();
        $this->fakeVerification($this->successfulVerification());

        $payment = app(PaymentReconciliationService::class)
            ->reconcile($this->payment->id);

        $this->assertSame('paid', $payment->status);

        $this->assertDatabaseHas('orders', [
            'id' => $payment->order_id,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        Http::assertSentCount(1);
    }

    public function test_payment_amount_mismatch_is_not_confirmed(): void
    {
        $this->makeOrderActive();

        $this->fakeVerification(
            $this->successfulVerification([
                'amount' => '2000.00',
            ])
        );

        $payment = app(PaymentReconciliationService::class)
            ->reconcile($this->payment->id);

        $this->assertSame('pending', $payment->status);
        $this->assertNotNull($payment->reconciliation_required_at);

        $this->assertDatabaseHas('orders', [
            'id' => $payment->order_id,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
    }

    public function test_payment_currency_mismatch_is_not_confirmed(): void
    {
        $this->makeOrderActive();

        $this->fakeVerification(
            $this->successfulVerification([
                'currency' => 'USD',
            ])
        );

        $payment = app(PaymentReconciliationService::class)
            ->reconcile($this->payment->id);

        $this->assertSame('pending', $payment->status);
        $this->assertNotNull($payment->reconciliation_required_at);

        $this->assertDatabaseHas('orders', [
            'id' => $payment->order_id,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
    }

    public function test_high_risk_payment_is_not_automatically_confirmed(): void
    {
        $this->makeOrderActive();

        $this->fakeVerification(
            $this->successfulVerification([
                'risk_level' => '1',
            ])
        );

        $payment = app(PaymentReconciliationService::class)
            ->reconcile($this->payment->id);

        $this->assertSame('pending', $payment->status);
        $this->assertNotNull($payment->reconciliation_required_at);

        $this->assertDatabaseHas('orders', [
            'id' => $payment->order_id,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
    }

    public function test_duplicate_gateway_reference_is_not_automatically_confirmed(): void
    {
        $this->makeOrderActive();

        $otherOrder = Order::create([
            'order_number' => 'ORD-RECON-002',
            'customer_name' => 'Another Customer',
            'customer_email' => 'another@example.com',
            'customer_phone' => '01712345679',
            'shipping_address' => 'Dhaka, Bangladesh',
            'subtotal' => '2500.00',
            'shipping_fee' => '0.00',
            'total' => '2500.00',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'delivery_status' => 'pending',
        ]);

        $otherOrder->payments()->create([
            'gateway' => 'sslcommerz',
            'transaction_id' => 'PAY-OTHER-001',
            'gateway_payment_id' => 'SSL-RECOVER-001',
            'amount' => '2500.00',
            'currency' => 'BDT',
            'status' => 'paid',
            'verified_at' => now(),
            'paid_at' => now(),
        ]);

        $this->fakeVerification($this->successfulVerification());

        $payment = app(PaymentReconciliationService::class)
            ->reconcile($this->payment->id);

        $this->assertSame('pending', $payment->status);
        $this->assertNotNull($payment->reconciliation_required_at);

        $this->assertDatabaseHas('orders', [
            'id' => $payment->order_id,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
    }

    public function test_repeated_successful_verification_does_not_confirm_order_twice(): void
    {
        $this->makeOrderActive();
        $this->fakeVerification($this->successfulVerification());

        $service = app(PaymentReconciliationService::class);

        $first = $service->reconcile($this->payment->id);
        $second = $service->reconcile($this->payment->id);

        $this->assertSame('paid', $first->status);
        $this->assertSame('paid', $second->status);

        $this->assertDatabaseHas('orders', [
            'id' => $this->payment->order_id,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $this->assertSame(
            1,
            Payment::query()
                ->whereKey($this->payment->id)
                ->where('status', 'paid')
                ->count()
        );

        Http::assertSentCount(1);
    }

    public function test_invalid_late_payment_amount_is_not_marked_as_successful(): void
    {
        $this->fakeVerification(
            $this->successfulVerification([
                'amount' => '2000.00',
            ])
        );

        $payment = app(PaymentReconciliationService::class)
            ->reconcile($this->payment->id);

        $this->assertSame('pending', $payment->status);

        $this->assertNotSame(
            'late_successful_payment',
            $payment->metadata['reconciliation_reason'] ?? null
        );

        $this->assertNotNull($payment->reconciliation_required_at);

        $this->assertDatabaseHas('orders', [
            'id' => $payment->order_id,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
    }

    public function test_high_risk_late_payment_is_not_marked_as_successful(): void
    {
        $this->fakeVerification(
            $this->successfulVerification([
                'risk_level' => '1',
            ])
        );

        $payment = app(PaymentReconciliationService::class)
            ->reconcile($this->payment->id);

        $this->assertSame('pending', $payment->status);

        $this->assertNotSame(
            'late_successful_payment',
            $payment->metadata['reconciliation_reason'] ?? null
        );

        $this->assertNotNull($payment->reconciliation_required_at);

        $this->assertDatabaseHas('orders', [
            'id' => $payment->order_id,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
    }
}
