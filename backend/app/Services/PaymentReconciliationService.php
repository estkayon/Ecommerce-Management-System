<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PaymentReconciliationService
{
    public function __construct(
        private readonly SSLCommerzService $gateway,
        private readonly PaymentConfirmationService $confirmationService
    ) {}

    public function reconcile(int $paymentId): Payment
    {
        $payment = Payment::query()->findOrFail($paymentId);

        if ($payment->gateway !== 'sslcommerz') {
            throw new RuntimeException('Unsupported payment gateway.');
        }

        if (! in_array(
            $payment->status,
            ['initiating', 'pending', 'processing'],
            true
        )) {
            return $payment;
        }

        $verification = null;

        try {
            $verification = $this->gateway->verify(
                $payment->transaction_id
            );
        } catch (Throwable $exception) {
            Log::warning('Payment gateway verification failed', [
                'payment_id' => $payment->id,
                'exception' => $exception::class,
            ]);
        }

        $hasSuccessfulTransaction = $this->hasSuccessfulTransaction(
            $payment,
            $verification
        );

        if ($hasSuccessfulTransaction) {
            try {
                return $this->confirmationService->confirmVerified(
                    $payment->transaction_id,
                    $verification
                );
            } catch (Throwable $exception) {
                Log::warning('Automatic payment confirmation failed', [
                    'payment_id' => $payment->id,
                    'exception' => $exception::class,
                ]);
            }
        }

        return DB::transaction(function () use (
            $paymentId,
            $verification
        ) {
            $snapshot = Payment::query()
                ->findOrFail($paymentId);

            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($snapshot->order_id);

            $payment = Payment::query()
                ->lockForUpdate()
                ->findOrFail($paymentId);

            if (! in_array(
                $payment->status,
                ['initiating', 'pending', 'processing'],
                true
            )) {
                return $payment;
            }

            $metadata = $payment->metadata ?? [];

            $isExpiredPendingOrder = (
                $order->status === 'pending' &&
                $order->payment_status === 'pending' &&
                $order->expires_at !== null &&
                $order->expires_at->isPast()
            );

            if (
                $isExpiredPendingOrder &&
                $this->hasFullyValidatedSuccess(
                    $payment,
                    $order,
                    $verification
                )
            ) {
                $metadata['reconciliation_reason'] =
                    'late_successful_payment';

                $metadata['review_detected_at'] ??=
                    now()->toIso8601String();

                Log::warning('Late successful payment requires review', [
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                ]);
            }

            $payment->update([
                'last_checked_at' => now(),
                'reconciliation_required_at' =>
                    $payment->reconciliation_required_at ?? now(),
                'metadata' => $metadata,
            ]);

            return $payment->refresh();
        }, 3);
    }

    private function hasSuccessfulTransaction(
        Payment $payment,
        ?array $verification
    ): bool {
        if (
            ! is_array($verification) ||
            ($verification['APIConnect'] ?? null) !== 'DONE' ||
            ! is_array($verification['element'] ?? null) ||
            ! array_is_list($verification['element'])
        ) {
            return false;
        }

        $count = filter_var(
            $verification['no_of_trans_found'] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($count === false || $count < 1) {
            return false;
        }

        foreach ($verification['element'] as $transaction) {
            if (
                is_array($transaction) &&
                ($transaction['tran_id'] ?? null) ===
                    $payment->transaction_id &&
                in_array(
                    $transaction['status'] ?? null,
                    ['VALID', 'VALIDATED'],
                    true
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function hasFullyValidatedSuccess(
        Payment $payment,
        Order $order,
        ?array $verification
    ): bool {
        if (! $this->hasSuccessfulTransaction($payment, $verification)) {
            return false;
        }

        if (
            ! is_numeric($payment->amount) ||
            ! is_numeric($order->total) ||
            bccomp(
                (string) $payment->amount,
                (string) $order->total,
                2
            ) !== 0
        ) {
            return false;
        }

        foreach ($verification['element'] as $transaction) {
            if (
                ! is_array($transaction) ||
                ($transaction['tran_id'] ?? null) !==
                    $payment->transaction_id ||
                ! in_array(
                    $transaction['status'] ?? null,
                    ['VALID', 'VALIDATED'],
                    true
                )
            ) {
                continue;
            }

            $reference = $transaction['val_id'] ?? null;

            if (
                ! isset(
                    $transaction['amount'],
                    $transaction['currency'],
                    $transaction['risk_level']
                ) ||
                ! is_numeric($transaction['amount']) ||
                bccomp(
                    (string) $transaction['amount'],
                    (string) $payment->amount,
                    2
                ) !== 0 ||
                $transaction['currency'] !== $payment->currency ||
                (string) $transaction['risk_level'] !== '0' ||
                ! is_string($reference) ||
                $reference === ''
            ) {
                continue;
            }

            $referenceUsed = Payment::query()
                ->where('gateway', 'sslcommerz')
                ->where('gateway_payment_id', $reference)
                ->where('id', '!=', $payment->id)
                ->exists();

            if (! $referenceUsed) {
                return true;
            }
        }

        return false;
    }
}
