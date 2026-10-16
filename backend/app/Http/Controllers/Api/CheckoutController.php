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

        return response()->json([
            'message' => 'Order placed successfully.',
            'data' => $order,
        ], 201);
    }
}
