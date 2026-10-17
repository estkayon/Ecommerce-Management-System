<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderCancellationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ExpirePendingOrders extends Command
{
    protected $signature = 'orders:expire';

    protected $description = 'Cancel expired unpaid orders and restore reserved inventory';

    public function handle(OrderCancellationService $cancellationService): int
    {
        $processed = 0;
        $skipped = 0;
        $failed = 0;

        Order::query()
            ->where('status', 'pending')
            ->where('payment_status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (
                $cancellationService,
                &$processed,
                &$skipped,
                &$failed
            ) {
                foreach ($orders as $order) {
                    try {
                        $hasActivePayment = $order->payments()
                            ->whereIn('status', [
                                'initiating',
                                'pending',
                                'processing',
                            ])
                            ->exists();

                        if ($hasActivePayment) {
                            $skipped++;
                            continue;
                        }

                        $cancellationService->cancel($order->id);
                        $processed++;
                    } catch (ValidationException $exception) {
                        $skipped++;
                    } catch (Throwable $exception) {
                        $failed++;

                        Log::error('Failed to expire order', [
                            'order_id' => $order->id,
                            'exception' => $exception::class,
                        ]);
                    }
                }
            });

        $this->info(
            "Processed: {$processed}, Skipped: {$skipped}, Failed: {$failed}"
        );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
