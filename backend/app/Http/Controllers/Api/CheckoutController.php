<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCheckoutRequest;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkoutService
    ) {}

    public function store(StoreCheckoutRequest $request): JsonResponse
    {
        $order = $this->checkoutService->checkout(
            $request->validated()
        );

        $paymentAccessToken = $order->getRelation('paymentAccessToken');

        $order->unsetRelation('paymentAccessToken');

        return response()->json([
            'message' => 'Order placed successfully.',
            'data' => $order,
            'payment_access_token' => $paymentAccessToken,
        ], 201);
    }
}
