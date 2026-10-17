<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentConfirmationService;
use App\Services\PaymentReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Jobs\ReconcilePaymentJob;


class PaymentCallbackController extends Controller
{
    public function __construct(
    private readonly PaymentConfirmationService $confirmationService
    ) {}

    public function success(Request $request): JsonResponse
    {
        return $this->verifyPayment($request);
    }

    public function ipn(Request $request): JsonResponse
    {
        return $this->verifyPayment($request);
    }

    public function fail(Request $request): JsonResponse
    {
        return $this->reconcileNotification($request);
    }

    public function cancel(Request $request): JsonResponse
    {
        return $this->reconcileNotification($request);
    }

  
    private function reconcileNotification(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tran_id' => ['required', 'string', 'max:100'],
        ]);

        $payment = Payment::query()
            ->where('gateway', 'sslcommerz')
            ->where('transaction_id', $data['tran_id'])
            ->first();

        if (! $payment) {
            return response()->json([
                'message' => 'Payment not found.',
            ], 404);
        }

        if (in_array(
            $payment->status,
            ['initiating', 'pending', 'processing'],
            true
        )) {
            ReconcilePaymentJob::dispatch($payment->id);
        }

        return response()->json([
            'message' => 'Payment notification accepted.',
        ], 202);
    }


    private function verifyPayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tran_id' => ['required', 'string', 'max:100'],
        ]);

        try {
            $payment = $this->confirmationService->confirm(
                $data['tran_id']
            );

            return response()->json([
                'message' => 'Payment verified successfully.',
                'payment_id' => $payment->id,
                'status' => $payment->status,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Payment callback verification failed', [
                'exception' => $exception::class,
            ]);

            return response()->json([
                'message' => 'Payment could not be confirmed.',
            ], 422);
        }
    }
}
