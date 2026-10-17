<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ReconcilePaymentJob;
use App\Models\Payment;
use App\Services\PaymentConfirmationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentCallbackController extends Controller
{
    public function __construct(
        private readonly PaymentConfirmationService $confirmationService
    ) {}

    public function success(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return $this->verifyPaymentJson($request);
        }

        return $this->verifyPaymentRedirect($request);
    }

    public function ipn(Request $request): JsonResponse
    {
        return $this->verifyPaymentJson($request);
    }

    public function fail(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return $this->reconcileNotificationJson($request);
        }

        return $this->handleUnsuccessfulReturn($request);
    }

    public function cancel(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return $this->reconcileNotificationJson($request);
        }

        return $this->handleUnsuccessfulReturn($request);
    }

    private function verifyPaymentJson(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tran_id' => ['required', 'string', 'max:100'],
        ]);

        try {
            $payment = $this->confirmationService->confirm(
                $data['tran_id']
            );

            return response()->json([
                'message' => 'Payment verified successfully.',
                'payment_id' => $payment->id,
                'status' => $payment->status,
            ], 200);
        } catch (Throwable $exception) {
            Log::warning('Payment JSON verification failed', [
                'exception' => $exception::class,
            ]);

            return response()->json([
                'message' => 'Payment could not be confirmed.',
            ], 422);
        }
    }

    private function verifyPaymentRedirect(
        Request $request
    ): RedirectResponse {
        $payment = $this->findPayment($request);

        if (! $payment) {
            return $this->redirectToStore('unverified');
        }

        try {
            $confirmed = $this->confirmationService->confirm(
                $payment->transaction_id
            );

            $outcome = $confirmed->status === 'paid'
                ? 'verification-completed'
                : 'pending';

            return $this->redirectToStore(
                $outcome,
                $payment->order_id
            );
        } catch (Throwable $exception) {
            Log::warning('Payment browser verification failed', [
                'payment_id' => $payment->id,
                'exception' => $exception::class,
            ]);

            $payment->refresh();

            return $this->redirectToStore(
                $payment->status === 'paid'
                    ? 'verification-completed'
                    : 'pending',
                $payment->order_id
            );
        }
    }

    private function reconcileNotificationJson(
        Request $request
    ): JsonResponse {
        $payment = $this->findPayment($request);

        if (! $payment) {
            return response()->json([
                'message' => 'Payment not found.',
            ], 404);
        }

        $this->dispatchReconciliationIfNeeded($payment);

        return response()->json([
            'message' => 'Payment notification accepted.',
        ], 202);
    }

    private function handleUnsuccessfulReturn(
        Request $request
    ): RedirectResponse {
        $payment = $this->findPayment($request);

        if (! $payment) {
            return $this->redirectToStore('unverified');
        }

        $this->dispatchReconciliationIfNeeded($payment);

        return $this->redirectToStore(
            'pending',
            $payment->order_id
        );
    }

    private function dispatchReconciliationIfNeeded(
        Payment $payment
    ): void {
        if (in_array(
            $payment->status,
            ['initiating', 'pending', 'processing'],
            true
        )) {
            ReconcilePaymentJob::dispatch($payment->id);
        }
    }

    private function findPayment(Request $request): ?Payment
    {
        $data = $request->validate([
            'tran_id' => ['required', 'string', 'max:100'],
        ]);

        return Payment::query()
            ->where('gateway', 'sslcommerz')
            ->where('transaction_id', $data['tran_id'])
            ->first();
    }

    private function redirectToStore(
        string $result,
        ?int $orderId = null
    ): RedirectResponse {
        $baseUrl = rtrim(
            config('app.frontend_url'),
            '/'
        );

        $query = http_build_query([
            'result' => $result,
            'order' => $orderId,
        ]);

        return redirect()->away(
            $baseUrl . '/payment/result?' . $query,
            303
        );
    }
}
