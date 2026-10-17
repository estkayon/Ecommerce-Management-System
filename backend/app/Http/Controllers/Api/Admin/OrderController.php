<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderCancellationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderCancellationService $cancellationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => [
                'nullable', 'in:pending,confirmed,cancelled,expired',
            ],
            'payment_status' => [
                'nullable',
                'in:pending,paid,failed,refunded',
            ],
        ]);

        $orders = Order::query()
            ->when(
                $validated['search'] ?? null,
                function ($query, $search) {
                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('order_number', 'ilike', "%{$search}%")
                            ->orWhere('customer_name', 'ilike', "%{$search}%")
                            ->orWhere('customer_phone', 'ilike', "%{$search}%");
                    });
                }
            )
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) =>
                    $query->where('status', $status)
            )
            ->when(
                $validated['payment_status'] ?? null,
                fn ($query, $status) =>
                    $query->where('payment_status', $status)
            )
            ->latest('id')
            ->paginate(15);

        return response()->json($orders)
            ->header('Cache-Control', 'no-store');
    }

    public function show(Order $order): JsonResponse
    {
        $items = OrderItem::query()
            ->where('order_id', $order->id)
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => [
                'order' => $order,
                'items' => $items,
            ],
        ])->header('Cache-Control', 'no-store');
    }

    public function cancel(Order $order): JsonResponse
    {
        $order = $this->cancellationService->cancel($order->id);

        return response()->json([
            'message' => 'Order cancellation processed successfully.',
            'data' => $order,
        ]);
    }
}
