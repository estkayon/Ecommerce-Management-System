<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderCancellationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExpirePendingOrders extends Command
{
    protected $signature = 'orders:expire';

    protected $description = 'Cancel expired unpaid orders and restore reserved inventory';

    public function handle(OrderCancellationService $cancellationService): int
    {
        $processed = 0;
        $failed = 0;

        Order::query()
            ->where('status', 'pending')
            ->where('payment_status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($orders) use ($cancellationService, &$processed, &$failed) {
                foreach ($orders as $order) {
                    try {
                        $cancellationService->cancel($order->id);
                        $processed++;
                    } catch (Throwable $exception) {
                        $failed++;

                        Log::error('Failed to expire order', [
                            'order_id' => $order->id,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("Processed: {$processed}, Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
