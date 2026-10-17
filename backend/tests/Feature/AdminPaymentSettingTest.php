<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPaymentSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_payment_setting(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/payment-settings', [
            'sslcommerz_enabled' => false,
        ])->assertOk()
            ->assertJsonPath('data.sslcommerz_enabled', false);

        $this->assertDatabaseHas('settings', [
            'key' => 'sslcommerz_enabled',
        ]);

        $setting = Setting::where(
            'key',
            'sslcommerz_enabled'
        )->firstOrFail();

        $this->assertFalse($setting->value['enabled']);

        $this->getJson('/api/admin/payment-settings')
            ->assertOk()
            ->assertJsonPath('data.sslcommerz_enabled', false);
    }

    public function test_unauthenticated_user_cannot_update_payment_setting(): void
    {
        $this->patchJson('/api/admin/payment-settings', [
            'sslcommerz_enabled' => false,
        ])->assertUnauthorized();

        $this->assertDatabaseMissing('settings', [
            'key' => 'sslcommerz_enabled',
        ]);
    }

    public function test_non_admin_cannot_update_payment_setting(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/admin/payment-settings', [
            'sslcommerz_enabled' => false,
        ])->assertForbidden();

        $this->assertDatabaseMissing('settings', [
            'key' => 'sslcommerz_enabled',
        ]);
    }

    
public function test_only_admin_can_access_late_payment_reviews(): void
{
    $this->getJson('/api/admin/payment-reviews')
        ->assertUnauthorized();

    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/admin/payment-reviews')
        ->assertForbidden();

    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    Sanctum::actingAs($admin);

    $this->getJson('/api/admin/payment-reviews')
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);
}


public function test_admin_can_record_late_payment_review_decision(): void
{
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    Sanctum::actingAs($admin);

    $order = \App\Models\Order::create([
        'order_number' => 'ORD-REVIEW-001',
        'customer_name' => 'Review Customer',
        'customer_email' => 'review@example.com',
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
        'transaction_id' => 'PAY-REVIEW-001',
        'amount' => '2500.00',
        'currency' => 'BDT',
        'status' => 'pending',
        'metadata' => [
            'reconciliation_reason' => 'late_successful_payment',
        ],
        'reconciliation_required_at' => now(),
    ]);

    $this->patchJson(
        "/api/admin/payment-reviews/{$payment->id}",
        [
            'decision' => 'refund_required',
            'note' => 'Payment verified after the order expired. Refund investigation required.',
        ]
    )->assertOk()
        ->assertJsonPath(
            'data.manual_review.decision',
            'refund_required'
        );

    $payment->refresh();
    $order->refresh();

    $this->assertSame(
        'refund_required',
        $payment->metadata['manual_review']['decision']
    );

    $this->assertSame($admin->id, $payment->metadata['manual_review']['reviewed_by']);
    $this->assertSame('pending', $payment->status);
    $this->assertSame('pending', $order->status);
    $this->assertSame('pending', $order->payment_status);
}

}
