<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;
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

        try {
            $verification = $this->gateway->verify(
                $payment->transaction_id
            );
        } catch (Throwable $exception) {
            $verification = null;
        }

        
        if (
            is_array($verification) &&
            ($verification['APIConnect'] ?? null) === 'DONE' &&
            $payment->order()->where('status', 'pending')
                ->where('payment_status', 'pending')
                ->where('expires_at', '>', now())
                ->exists()
        ) {
            $hasSuccessfulTransaction = collect(
                $verification['element'] ?? []
            )->contains(
                fn ($transaction) =>
                    is_array($transaction) &&
                    ($transaction['tran_id'] ?? null) === $payment->transaction_id &&
                    in_array(
                        $transaction['status'] ?? null,
                        ['VALID', 'VALIDATED'],
                        true
                    )
            );

            if ($hasSuccessfulTransaction) {
                try {
                    return $this->confirmationService->confirmVerified(
                        $payment->transaction_id,
                        $verification
                    );
                } catch (Throwable $exception) {
                    // Leave uncertain payments for reconciliation review.
                }
            }
        }


        return DB::transaction(function () use (
            $paymentId,
            $verification
        ) {
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

            $payment->update([
                'last_checked_at' => now(),
                'reconciliation_required_at' => now(),
            ]);

            return $payment->refresh();
        }, 3);
    }
}
