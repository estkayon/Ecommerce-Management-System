<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;

class PaymentReviewController extends Controller
{
    public function index(): JsonResponse
    {
        $payments = Payment::query()
            ->with('order:id,order_number,customer_name,total,status,payment_status,expires_at')
            ->where(
                'metadata->reconciliation_reason',
                'late_successful_payment'
            )
            ->whereIn('status', [
                'initiating',
                'pending',
                'processing',
            ])
            ->whereNotNull('reconciliation_required_at')
            ->orderBy('id', 'desc')
            ->paginate(20);

        $items = $payments->getCollection()->map(
            function (Payment $payment): array {
                return [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'order_number' => $payment->order?->order_number,
                    'customer_name' => $payment->order?->customer_name,
                    'transaction_id' => $payment->transaction_id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'payment_status' => $payment->status,
                    'order_status' => $payment->order?->status,
                    'review_reason' =>
                        $payment->metadata['reconciliation_reason'] ?? null,
                    'review_detected_at' =>
                        $payment->metadata['review_detected_at'] ?? null,
                    'last_checked_at' =>
                        $payment->last_checked_at?->toIso8601String(),
                    'expires_at' =>
                        $payment->order?->expires_at?->toIso8601String(),
                ];
            }
        );

        return response()->json([
            'data' => $items->values(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }
}
