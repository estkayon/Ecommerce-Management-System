<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderCancellationService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderCancellationService $cancellationService
    ) {}

    public function cancel(Order $order): JsonResponse
    {
        $order = $this->cancellationService->cancel($order->id);

        return response()->json([
            'message' => 'Order cancellation processed successfully.',
            'data' => $order,
        ]);
    }
}
