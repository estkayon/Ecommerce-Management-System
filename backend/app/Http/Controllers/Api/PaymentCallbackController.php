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

    public function success(Request $request): RedirectResponse
    {
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

    public function ipn(Request $request): JsonResponse
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
            ]);
        } catch (Throwable $exception) {
            Log::warning('Payment IPN verification failed', [
                'exception' => $exception::class,
            ]);

            return response()->json([
                'message' => 'Payment could not be confirmed.',
            ], 422);
        }
    }

    public function fail(Request $request): RedirectResponse
    {
        return $this->handleUnsuccessfulReturn($request);
    }

    public function cancel(Request $request): RedirectResponse
    {
        return $this->handleUnsuccessfulReturn($request);
    }

    private function handleUnsuccessfulReturn(
        Request $request
    ): RedirectResponse {
        $payment = $this->findPayment($request);

        if (! $payment) {
            return $this->redirectToStore('unverified');
        }

        if (in_array(
            $payment->status,
            ['initiating', 'pending', 'processing'],
            true
        )) {
            ReconcilePaymentJob::dispatch($payment->id);
        }

        return $this->redirectToStore(
            'pending',
            $payment->order_id
        );
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
            $baseUrl.'/payment/result?'.$query,
            303
        );
    }
}
