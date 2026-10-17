<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use App\Models\Setting;

class PaymentService
{
    public function __construct(
        private readonly SSLCommerzService $gateway
    ) {}

    public function initiate(Order $order, array $callbackUrls): array
    {

    
        $setting = Setting::query()
            ->where('key', 'sslcommerz_enabled')
            ->first();

        $enabled = $setting
            ? ($setting->value['enabled'] ?? false) === true
            : true;

        if (! $enabled) {
            throw new RuntimeException(
                'SSLCommerz payments are currently disabled.'
            );
        }


        $payment = DB::transaction(function () use ($order) {
            $lockedOrder = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            if (
                $lockedOrder->status !== 'pending' ||
                $lockedOrder->payment_status !== 'pending' ||
                ($lockedOrder->expires_at &&
                    $lockedOrder->expires_at->isPast())
            ) {
                throw new RuntimeException(
                    'Order is not eligible for payment.'
                );
            }

            $activePaymentExists = Payment::query()
                ->where('order_id', $lockedOrder->id)
                ->whereIn('status', ['initiating', 'pending', 'processing'])
                ->exists();

            if ($activePaymentExists) {
                throw new RuntimeException(
                    'An active payment attempt already exists.'
                );
            }

            return Payment::create([
                'order_id' => $lockedOrder->id,
                'gateway' => 'sslcommerz',
                'transaction_id' => 'PAY-'.Str::ulid(),
                'amount' => $lockedOrder->total,
                'currency' => 'BDT',
                'status' => 'initiating',
            ]);
        });

       
        try {
            $session = $this->gateway->initiate(
                $order->fresh(),
                $payment,
                $callbackUrls
            );

            $updated = Payment::query()
                ->whereKey($payment->id)
                ->where('status', 'initiating')
                ->update([
                    'status' => 'pending',
                    'metadata' => [
                        'session_key' => $session['session_key'],
                    ],
                ]);

            $payment->refresh();

            if (
                $updated === 0 &&
                $payment->status !== 'paid'
            ) {
                throw new RuntimeException(
                    'Payment state changed during initiation.'
                );
            }

            return [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'gateway_url' => $session['gateway_url'],
            ];
        }  catch (Throwable $exception) {
    $isUncertain = $exception instanceof RuntimeException &&
        $exception->getMessage() ===
        'Payment gateway connection is uncertain.';

    if ($isUncertain) {
        Payment::query()
            ->whereKey($payment->id)
            ->where('status', 'initiating')
            ->update([
                'reconciliation_required_at' => now(),
                'failure_reason' => null,
            ]);
    } else {
        Payment::query()
            ->whereKey($payment->id)
            ->where('status', 'initiating')
            ->update([
                'status' => 'failed',
                'failure_reason' => 'Payment initiation failed.',
            ]);
    }

    throw $exception;
}

}
}