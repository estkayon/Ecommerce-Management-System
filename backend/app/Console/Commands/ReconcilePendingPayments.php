<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\PaymentReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReconcilePendingPayments extends Command
{
    protected $signature = 'payments:reconcile';

    protected $description = 'Check active payments requiring reconciliation';

    public function handle(
        PaymentReconciliationService $reconciliationService
    ): int {
        $processed = 0;
        $failed = 0;

        Payment::query()
            ->whereIn('status', ['initiating', 'pending', 'processing'])
            ->where('created_at', '<=', now()->subMinutes(5))
            ->where(function ($query) {
                $query->whereNull('last_checked_at')
                    ->orWhere('last_checked_at', '<=', now()->subMinutes(5));
            })
            ->whereHas('order', function ($query) {
                $query->where('status', 'pending')
                    ->where('payment_status', 'pending');
            })
            ->orderBy('id')
            ->chunkById(100, function ($payments) use (
                $reconciliationService,
                &$processed,
                &$failed
            ) {
                foreach ($payments as $payment) {
                    try {
                        $reconciliationService->reconcile($payment->id);
                        $processed++;
                    } catch (Throwable $exception) {
                        $failed++;

                        Log::error('Payment reconciliation failed', [
                            'payment_id' => $payment->id,
                            'exception' => $exception::class,
                        ]);
                    }
                }
            });

        $this->info("Processed: {$processed}, Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
