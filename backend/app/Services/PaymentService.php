<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PaymentService
{
    public function __construct(
        private readonly SSLCommerzService $gateway
    ) {}

    public function initiate(Order $order, array $callbackUrls): array
    {
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

            $payment->update([
                'status' => 'pending',
                'metadata' => [
                    'session_key' => $session['session_key'],
                ],
            ]);

            return [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'gateway_url' => $session['gateway_url'],
            ];
        } catch (Throwable $exception) {
            $payment->update([
                'status' => 'failed',
                'failure_reason' => 'Payment initiation failed.',
            ]);

            throw $exception;
        }
    }
}
