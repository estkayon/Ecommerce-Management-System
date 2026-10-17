<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentResolutionController extends Controller
{
    public function update(Request $request, Payment $payment): JsonResponse
    {
        $validated = $request->validate([
            'decision' => [
                'required',
                'in:investigating,refund_required',
            ],
            'note' => [
                'required',
                'string',
                'min:10',
                'max:1000',
            ],
        ]);

        $payment = DB::transaction(function () use (
            $payment,
            $validated,
            $request
        ) {
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($payment->order_id);

            $lockedPayment = Payment::query()
                ->lockForUpdate()
                ->findOrFail($payment->id);

            $metadata = $lockedPayment->metadata ?? [];

            if (
                ($metadata['reconciliation_reason'] ?? null)
                    !== 'late_successful_payment' ||
                ! in_array(
                    $lockedPayment->status,
                    ['initiating', 'pending', 'processing'],
                    true
                ) ||
                $order->status !== 'pending' ||
                $order->payment_status !== 'pending'
            ) {
                throw ValidationException::withMessages([
                    'payment' => [
                        'Payment is not eligible for manual review.',
                    ],
                ]);
            }

            $metadata['manual_review'] = [
                'decision' => $validated['decision'],
                'note' => $validated['note'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now()->toIso8601String(),
            ];

            $lockedPayment->update([
                'metadata' => $metadata,
            ]);

            return $lockedPayment->refresh();
        }, 3);

        return response()->json([
            'message' => 'Payment review decision saved.',
            'data' => [
                'payment_id' => $payment->id,
                'payment_status' => $payment->status,
                'manual_review' => $payment->metadata['manual_review'],
            ],
        ]);
    }
}
