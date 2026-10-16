<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentConfirmationService
{
    public function __construct(
        private readonly SSLCommerzService $gateway
    ) {}

    public function confirm(string $transactionId): Payment
    {
        $payment = Payment::query()
            ->where('gateway', 'sslcommerz')
            ->where('transaction_id', $transactionId)
            ->firstOrFail();

        if ($payment->status === 'paid') {
            return $payment;
        }

        $verification = $this->gateway->verify($transactionId);

        return $this->confirmVerified($transactionId, $verification);
        }

        public function confirmVerified(
            string $transactionId,
            array $verification
        ): Payment {
            $payment = Payment::query()
                ->where('gateway', 'sslcommerz')
                ->where('transaction_id', $transactionId)
                ->firstOrFail();

            return DB::transaction(function () use (
                $payment,
                $transactionId,
                $verification
            ) {
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($payment->order_id);

            $lockedPayment = Payment::query()
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($lockedPayment->status === 'paid') {
                return $lockedPayment;
            }

            if (! in_array($lockedPayment->status, ['pending', 'processing'], true)) {
                throw new RuntimeException(
                    'Payment attempt is not eligible for confirmation.'
                );
            }

           if (
                $order->status !== 'pending' ||
                $order->payment_status !== 'pending' ||
                ($order->expires_at && $order->expires_at->isPast())
            ) {
                throw new RuntimeException(
                    'Order is not eligible for payment confirmation.'
                );
            }

            
            if (($verification['APIConnect'] ?? null) !== 'DONE') {
                throw new RuntimeException(
                    'Gateway transaction verification was unsuccessful.'
                );
            }

            if (
                ! isset($verification['no_of_trans_found']) ||
                ! is_numeric($verification['no_of_trans_found']) ||
                (int) $verification['no_of_trans_found'] < 1
            ) {
                throw new RuntimeException(
                    'No verified gateway transactions found.'
                );
            }


            $transactions = $verification['element'] ?? [];

            if (! is_array($transactions)) {
                throw new RuntimeException(
                    'Invalid gateway verification response.'
                );
            }

            
            if (! array_is_list($transactions)) {
                throw new RuntimeException(
                    'Invalid gateway transaction list.'
                );
            }

            $reportedCount = filter_var(
                $verification['no_of_trans_found'],
                FILTER_VALIDATE_INT
            );

            if (
                $reportedCount === false ||
                $reportedCount < 1 ||
                count($transactions) < 1
            ) {
                throw new RuntimeException(
                    'Gateway verification returned no valid transaction records.'
                );
            }


            $verifiedTransaction = collect($transactions)->first(
                fn ($transaction) =>
                    is_array($transaction) &&
                    ($transaction['tran_id'] ?? null) === $transactionId &&
                    in_array(
                        $transaction['status'] ?? null,
                        ['VALID', 'VALIDATED'],
                        true
                    )
            );

            if (! $verifiedTransaction) {
                throw new RuntimeException(
                    'Gateway did not confirm the transaction.'
                );
            }

            if (
                ! isset(
                    $verifiedTransaction['amount'],
                    $verifiedTransaction['currency']
                ) ||
                ! is_numeric($verifiedTransaction['amount']) ||
                bccomp(
                    (string) $verifiedTransaction['amount'],
                    $lockedPayment->amount,
                    2
                ) !== 0 ||
                $verifiedTransaction['currency'] !== $lockedPayment->currency ||
                bccomp($lockedPayment->amount, $order->total, 2) !== 0
            ) {
                throw new RuntimeException(
                    'Payment amount or currency mismatch.'
                );
            }

            $gatewayPaymentId = $verifiedTransaction['val_id'] ?? null;
            
            $riskLevel = $verifiedTransaction['risk_level'] ?? null;

            if ($riskLevel === null) {
                throw new RuntimeException(
                    'Gateway risk information is missing.'
                );
            }

            if ((string) $riskLevel !== '0') {
                throw new RuntimeException(
                    'Gateway flagged the payment for risk review.'
                );
            }


            if (! is_string($gatewayPaymentId) ||
                $gatewayPaymentId === '') {
                throw new RuntimeException(
                    'Missing gateway payment reference.'
                );
            }

            
            $referenceAlreadyUsed = Payment::query()
                ->where('gateway', 'sslcommerz')
                ->where('gateway_payment_id', $gatewayPaymentId)
                ->where('id', '!=', $lockedPayment->id)
                ->exists();

            if ($referenceAlreadyUsed) {
                throw new RuntimeException(
                    'Gateway payment reference is already associated with another payment.'
                );
            }


            $lockedPayment->update([
                'status' => 'paid',
                'gateway_payment_id' => $gatewayPaymentId,
                'verified_at' => now(),
                'paid_at' => now(),
                'failure_reason' => null,
            ]);

            $order->update([
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'paid_at' => now(),
            ]);

            return $lockedPayment->refresh();
        }, 3);
    }
}
