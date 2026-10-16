<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentConfirmationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        return response()->json([
            'message' => 'Payment failure notification received.',
        ], 202);
    }

    public function cancel(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Payment cancellation notification received.',
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
