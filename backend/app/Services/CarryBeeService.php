<?php

namespace App\Services;

use App\Models\Order;
use RuntimeException;

class CarryBeeService
{
    public function createDelivery(Order $order): array
    {
        if (! config('services.carrybee.enabled')) {
            throw new RuntimeException(
                'CarryBee delivery integration is disabled.'
            );
        }

        $mode = config('services.carrybee.mode', 'mock');

        if ($mode === 'mock') {
            return $this->createMockDelivery($order);
        }

        throw new RuntimeException(
            'Real CarryBee API integration requires verified API documentation and credentials.'
        );
    }

    private function createMockDelivery(Order $order): array
    {
        if (
            $order->status !== 'confirmed' ||
            $order->payment_status !== 'paid'
        ) {
            throw new RuntimeException(
                'Only confirmed and paid orders can be dispatched.'
            );
        }

        return [
            'consignment_id' => 'MOCK-CB-'.$order->id,
            'tracking_number' => 'MOCK-TRK-'.$order->id,
            'status' => 'created',
            'metadata' => [
                'mode' => 'mock',
                'provider' => 'carrybee',
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'shipping_address' => $order->shipping_address,
                'total' => $order->total,
                'items' => $order->items->map(
                    fn ($item) => [
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                    ]
                )->values()->all(),
            ],
        ];
    }
}
