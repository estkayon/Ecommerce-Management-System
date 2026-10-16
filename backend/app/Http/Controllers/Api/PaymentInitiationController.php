<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PaymentInitiationController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService
    ) {}

    public function store(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'payment_access_token' => [
                'required',
                'string',
                'size:64',
            ],
        ]);

        $providedHash = hash(
            'sha256',
            $validated['payment_access_token']
        );

        if (
            ! $order->payment_access_token_hash ||
            ! hash_equals(
                $order->payment_access_token_hash,
                $providedHash
            )
        ) {
            throw ValidationException::withMessages([
                'payment_access_token' => [
                    'Invalid payment access token.',
                ],
            ]);
        }

        $callbackUrls = [
            'success' => route('payments.sslcommerz.success'),
            'fail' => route('payments.sslcommerz.fail'),
            'cancel' => route('payments.sslcommerz.cancel'),
            'ipn' => route('payments.sslcommerz.ipn'),
        ];

        try {
            $result = $this->paymentService->initiate(
                $order,
                $callbackUrls
            );

            return response()->json([
                'message' => 'Payment initiated successfully.',
                'data' => $result,
            ], 201);
        } catch (RuntimeException $exception) {
            Log::warning('Payment initiation rejected', [
                'order_id' => $order->id,
                'reason' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Payment initiation could not be completed.',
            ], 422);
        }
    }
}
