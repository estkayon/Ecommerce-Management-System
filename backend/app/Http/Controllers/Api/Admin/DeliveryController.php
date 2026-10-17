<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Jobs\DispatchCarryBeeDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'in:pending,processing,created,dispatched,delivered,failed'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $deliveries = Delivery::query()
            ->with([
                'order:id,order_number,customer_name,customer_phone,status,payment_status,delivery_status',
            ])
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->latest('id')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json($deliveries);
    }

    public function show(Delivery $delivery): JsonResponse
    {
        $delivery->load([
            'order',
            'order.items',
        ]);

        return response()->json([
            'data' => $delivery,
        ]);
    }

    
public function retry(Delivery $delivery): JsonResponse
{
    $orderId = DB::transaction(function () use ($delivery) {
        $order = \App\Models\Order::query()
            ->lockForUpdate()
            ->findOrFail($delivery->order_id);

        $lockedDelivery = Delivery::query()
            ->lockForUpdate()
            ->findOrFail($delivery->id);

        if (
            $order->status !== 'confirmed' ||
            $order->payment_status !== 'paid' ||
            $lockedDelivery->status !== 'failed' ||
            $lockedDelivery->consignment_id !== null
        ) {
            throw ValidationException::withMessages([
                'delivery' => [
                    'Only failed deliveries without a consignment can be retried.',
                ],
            ]);
        }

        if (
            ! config('services.carrybee.enabled') ||
            config('services.carrybee.mode') !== 'mock'
        ) {
            throw ValidationException::withMessages([
                'delivery' => ['Mock delivery integration must be enabled.'],
            ]);
        }

        $lockedDelivery->update([
            'status' => 'pending',
            'last_error' => null,
        ]);

        DispatchCarryBeeDelivery::dispatch($order->id)->afterCommit();

        return $order->id;
    }, 3);

    return response()->json([
        'message' => 'Delivery retry queued successfully.',
        'order_id' => $orderId,
    ], 202);
}

}
