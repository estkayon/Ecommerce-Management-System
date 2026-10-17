<?php

namespace App\Jobs;

use App\Models\Delivery;
use App\Models\Order;
use App\Services\CarryBeeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DispatchCarryBeeDelivery implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [60, 180];

    public function __construct(
        public readonly int $orderId
    ) {}

    public function handle(CarryBeeService $carryBeeService): void
    {
        $order = Order::query()
            ->with('items')
            ->findOrFail($this->orderId);

        if (
            $order->status !== 'confirmed' ||
            $order->payment_status !== 'paid'
        ) {
            Log::warning('Delivery dispatch skipped: order not paid', [
                'order_id' => $order->id,
            ]);

            return;
        }

        if (! config('services.carrybee.enabled')) {
            return;
        }

        if (config('services.carrybee.mode') !== 'mock') {
            throw new RuntimeException(
                'Real CarryBee dispatch is not configured.'
            );
        }

        $delivery = DB::transaction(function () use ($order) {
            $lockedOrder = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            if (
                $lockedOrder->status !== 'confirmed' ||
                $lockedOrder->payment_status !== 'paid'
            ) {
                return null;
            }

            $delivery = Delivery::query()
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->first();

            if (! $delivery) {
                $delivery = Delivery::create([
                    'order_id' => $order->id,
                    'provider' => 'carrybee',
                    'status' => 'pending',
                ]);
            }

            if (
                $delivery->consignment_id !== null ||
                in_array(
                    $delivery->status,
                    ['created', 'dispatched', 'delivered'],
                    true
                )
            ) {
                return null;
            }

            $delivery->update([
                'status' => 'processing',
                'attempts' => $delivery->attempts + 1,
                'last_error' => null,
            ]);

            return $delivery;
        }, 3);

        if (! $delivery) {
            return;
        }

        try {
            $result = $carryBeeService->createDelivery($order);

            DB::transaction(function () use ($order, $result) {
                $lockedOrder = Order::query()
                    ->lockForUpdate()
                    ->findOrFail($order->id);

                if (
                    $lockedOrder->status !== 'confirmed' ||
                    $lockedOrder->payment_status !== 'paid'
                ) {
                    throw new RuntimeException(
                        'Order is no longer eligible for delivery.'
                    );
                }

                $delivery = Delivery::query()
                    ->where('order_id', $order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($delivery->consignment_id !== null) {
                    return;
                }

                $delivery->update([
                    'status' => 'created',
                    'consignment_id' => $result['consignment_id'],
                    'tracking_number' => $result['tracking_number'],
                    'dispatched_at' => now(),
                    'last_error' => null,
                    'metadata' => $result['metadata'],
                ]);

                $lockedOrder->update([
                    'delivery_status' => 'created',
                ]);
            }, 3);

            Log::info('Mock CarryBee delivery created', [
                'order_id' => $order->id,
            ]);
        } catch (Throwable $exception) {
            DB::transaction(function () use ($order, $exception) {
                Order::query()
                    ->lockForUpdate()
                    ->findOrFail($order->id);

                $delivery = Delivery::query()
                    ->where('order_id', $order->id)
                    ->lockForUpdate()
                    ->first();

                if ($delivery && $delivery->consignment_id === null) {
                    $delivery->update([
                        'status' => 'failed',
                        'last_error' => mb_substr(
                            $exception->getMessage(),
                            0,
                            1000
                        ),
                    ]);
                }
            }, 3);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('CarryBee delivery job failed', [
            'order_id' => $this->orderId,
            'exception' => $exception
                ? get_class($exception)
                : null,
        ]);
    }
}
