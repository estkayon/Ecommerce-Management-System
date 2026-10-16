<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderCancellationService
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    public function cancel(int $orderId): Order
    {
        return DB::transaction(function () use ($orderId) {
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($orderId);

            if ($order->status === 'cancelled') {
                return $order;
            }

            if (
                $order->status !== 'pending' ||
                $order->payment_status !== 'pending'
            ) {
                throw ValidationException::withMessages([
                    'order' => [
                        'This order cannot be cancelled in its current state.',
                    ],
                ]);
            }

            $hasActivePayment = $order->payments()
                ->whereIn('status', ['initiating', 'pending', 'processing'])
                ->exists();

            if ($hasActivePayment) {
                throw ValidationException::withMessages([
                    'order' => [
                        'This order has an active payment attempt.',
                    ],
                ]);
            }

            foreach ($order->items()->orderBy('product_id')->get() as $item) {
                $this->inventoryService->adjustStock(
                    productId: $item->product_id,
                    quantityChange: $item->quantity,
                    reason: 'Stock restored after order cancellation',
                    type: 'order_cancellation',
                    referenceType: 'order',
                    referenceId: $order->id
                );
            }

            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            return $order->refresh();
        }, 3);
    }
}
