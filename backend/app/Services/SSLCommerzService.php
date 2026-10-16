<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SSLCommerzService
{
    public function __construct(
        private readonly HttpFactory $http
    ) {}

    private function baseUrl(): string
    {
        $config = config('services.sslcommerz');

        return rtrim(
            $config['sandbox']
                ? $config['sandbox_url']
                : $config['live_url'],
            '/'
        );
    }

    public function initiate(
        Order $order,
        Payment $payment,
        array $callbackUrls
    ): array {
        if (! $order->customer_email) {
            throw new RuntimeException(
                'Customer email is required for payment.'
            );
        }

        if (
            bccomp($order->total, '10.00', 2) < 0 ||
            bccomp($order->total, '500000.00', 2) > 0
        ) {
            throw new RuntimeException(
                'Order amount is outside gateway limits.'
            );
        }

        $transactionId = $payment->transaction_id;

        if (! $transactionId) {
            throw new RuntimeException(
                'Payment transaction ID is missing.'
            );
        }

        $payload = [
            'store_id' => config('services.sslcommerz.store_id'),
            'store_passwd' => config('services.sslcommerz.store_password'),
            'total_amount' => $order->total,
            'currency' => 'BDT',
            'tran_id' => $transactionId,

            'success_url' => $callbackUrls['success'],
            'fail_url' => $callbackUrls['fail'],
            'cancel_url' => $callbackUrls['cancel'],
            'ipn_url' => $callbackUrls['ipn'],

            'cus_name' => $order->customer_name,
            'cus_email' => $order->customer_email,
            'cus_phone' => $order->customer_phone,
            'cus_add1' => $order->shipping_address,
            'cus_country' => 'Bangladesh',

            
            'shipping_method' => 'YES',
            'num_of_item' => $order->items()->sum('quantity'),
            'ship_name' => $order->customer_name,
            'ship_add1' => $order->shipping_address,
            'ship_city' => 'Dhaka',
            'ship_postcode' => '1000',
            'ship_country' => 'Bangladesh',


            'product_name' => 'Order '.$order->order_number,
            'product_category' => 'General',
            'product_profile' => 'general',
        ];

        $response = $this->http
            ->asForm()
            ->connectTimeout(10)
            ->timeout(30)
            ->post(
                $this->baseUrl().'/gwprocess/v4/api.php',
                $payload
            );

        if (
            $response->failed() ||
            $response->json('status') !== 'SUCCESS' ||
            ! $response->json('GatewayPageURL')
        ) {
            Log::warning('SSLCommerz initiation rejected', [
                'http_status' => $response->status(),
                'gateway_status' => $response->json('status'),
                'failed_reason' => $response->json('failedreason'),
            ]);

            throw new RuntimeException(
                'Unable to initiate payment.'
            );
        }

        return [
            'transaction_id' => $transactionId,
            'session_key' => $response->json('sessionkey'),
            'gateway_url' => $response->json('GatewayPageURL'),
        ];
    }

    public function verify(string $transactionId): array
    {
        $response = $this->http
            ->connectTimeout(10)
            ->timeout(30)
            ->get(
                $this->baseUrl().'/validator/api/merchantTransIDvalidationAPI.php',
                [
                    'tran_id' => $transactionId,
                    'store_id' => config('services.sslcommerz.store_id'),
                    'store_passwd' => config('services.sslcommerz.store_password'),
                    'format' => 'json',
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Payment verification request failed.'
            );
        }

        $result = $response->json();

        if (! is_array($result)) {
            throw new RuntimeException(
                'Invalid payment verification response.'
            );
        }

        return $result;
    }
}
