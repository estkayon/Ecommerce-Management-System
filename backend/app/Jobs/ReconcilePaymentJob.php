<?php

namespace App\Jobs;

use App\Services\PaymentReconciliationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReconcilePaymentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public array $backoff = [60, 180];

    public function __construct(
        public readonly int $paymentId
    ) {}

    public function handle(
        PaymentReconciliationService $reconciliationService
    ): void {
        $reconciliationService->reconcile($this->paymentId);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Payment reconciliation queue job failed', [
            'payment_id' => $this->paymentId,
            'exception' => $exception ? get_class($exception) : null,
        ]);
    }
}
