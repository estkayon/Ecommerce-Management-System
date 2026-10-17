<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentResolutionService
{
    public function __construct(
        private readonly SSLCommerzService $gateway
    ) {}

    public function resolveUnsuccessful(
        int $paymentId,
        int $adminId,
        string $note
    ): Payment {
        $payment = Payment::query()->findOrFail($paymentId);

        if (
            $payment->gateway !== 'sslcommerz' ||
            ! in_array(
                $payment->status,
                ['initiating', 'pending', 'processing'],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'payment' => ['Payment is not eligible for resolution.'],
            ]);
        }

        try {
            $verification = $this->gateway->verify(
                $payment->transaction_id
            );
        } catch (Throwable $exception) {
            Log::warning('Payment resolution verification failed', [
                'payment_id' => $paymentId,
                'exception' => $exception::class,
            ]);

            throw ValidationException::withMessages([
                'payment' => [
                    'Gateway verification is unavailable. Payment remains unresolved.',
                ],
            ]);
        }

        if (! $this->hasUnsuccessfulEvidence($payment, $verification)) {
            throw ValidationException::withMessages([
                'payment' => [
                    'Gateway verification does not establish an unsuccessful payment.',
                ],
            ]);
        }

        return DB::transaction(function () use (
            $paymentId,
            $adminId,
            $note,
            $verification
        ) {
            $snapshot = Payment::query()->findOrFail($paymentId);

            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($snapshot->order_id);

            $payment = Payment::query()
                ->lockForUpdate()
                ->findOrFail($paymentId);

            $metadata = $payment->metadata ?? [];

            if (
                $order->status !== 'pending' ||
                $order->payment_status !== 'pending' ||
                ! in_array(
                    $payment->status,
                    ['initiating', 'pending', 'processing'],
                    true
                ) ||
                ($metadata['reconciliation_reason'] ?? null)
                    === 'late_successful_payment' ||
                ($metadata['manual_review']['decision'] ?? null)
                    !== 'confirmed_unsuccessful'
            ) {
                throw ValidationException::withMessages([
                    'payment' => [
                        'Payment or review state is not eligible for resolution.',
                    ],
                ]);
            }

            $metadata['resolution'] = [
                'type' => 'confirmed_unsuccessful',
                'resolved_by' => $adminId,
                'resolved_at' => now()->toIso8601String(),
                'note' => $note,
                'verification_source' => 'sslcommerz_transaction_query',
            ];

            $payment->update([
                'status' => 'failed',
                'failure_reason' =>
                    'Resolved after gateway verification and admin review.',
                'verified_at' => now(),
                'last_checked_at' => now(),
                'metadata' => $metadata,
            ]);

            return $payment->refresh();
        }, 3);
    }

    private function hasUnsuccessfulEvidence(
        Payment $payment,
        array $verification
    ): bool {
        if (
            ($verification['APIConnect'] ?? null) !== 'DONE' ||
            ! is_array($verification['element'] ?? null) ||
            ! array_is_list($verification['element'])
        ) {
            return false;
        }

        $transactions = $verification['element'];

        $count = filter_var(
            $verification['no_of_trans_found'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $count === false ||
            $count < 1 ||
            count($transactions) !== $count
        ) {
            return false;
        }

        foreach ($transactions as $transaction) {
            if (
                ! is_array($transaction) ||
                ($transaction['tran_id'] ?? null)
                    !== $payment->transaction_id ||
                ! in_array(
                    strtoupper((string) ($transaction['status'] ?? '')),
                    ['FAILED', 'CANCELLED'],
                    true
                )
            ) {
                return false;
            }
        }

        return true;
    }
}
