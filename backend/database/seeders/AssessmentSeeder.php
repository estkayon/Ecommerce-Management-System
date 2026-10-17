<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AssessmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'Assessment demo data is allowed only in local/testing.'
            );
        }

        $names = [
            'Wireless Mouse',
            'Mechanical Keyboard',
            'USB-C Hub',
            'Bluetooth Speaker',
            'Gaming Headset',
            'Laptop Stand',
            'Webcam Full HD',
            'Portable SSD',
            'USB Flash Drive',
            'Wireless Charger',
            'Smart Watch',
            'Fitness Band',
            'Power Bank',
            'Phone Stand',
            'Laptop Backpack',
            'Noise Cancelling Earbuds',
            'LED Desk Lamp',
            'Gaming Mouse Pad',
            'HDMI Cable',
            'Ethernet Cable',
            'WiFi Router',
            'Bluetooth Adapter',
            'USB Microphone',
            'Monitor Light Bar',
            'Laptop Cooling Pad',
            'External HDD',
            'Graphics Tablet',
            'Portable Monitor',
            'Ergonomic Keyboard',
            'Type-C Cable',
            'Phone Tripod',
            'Ring Light',
            'Mini Projector',
            'Smart Plug',
            'Security Camera',
            'Wireless Presenter',
        ];

        $products = [];

        DB::transaction(function () use ($names, &$products) {
            foreach ($names as $index => $name) {
                $number = $index + 1;

                $product = Product::withTrashed()->updateOrCreate(
                    ['sku' => sprintf('DEMO-%04d', $number)],
                    [
                        'name' => $name,
                        'description' => 'Demo product for the e-commerce technical assessment.',
                        'price' => number_format(
                            450 + ($index * 175),
                            2,
                            '.',
                            ''
                        ),
                        'is_active' => true,
                        'deleted_at' => null,
                    ]
                );

                DB::table('inventories')->insertOrIgnore([
                    'product_id' => $product->id,
                    'quantity' => 5000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $products[] = $product;
            }
        }, 3);

        $inventoryService = app(InventoryService::class);

        for ($i = 1; $i <= 105; $i++) {
            $orderNumber = sprintf('DEMO-ORDER-%04d', $i);

            if (Order::where('order_number', $orderNumber)->exists()) {
                continue;
            }

            $isPending = $i > 95;
            $isCancelled = $i > 70 && $i <= 95;

            $createdAt = $isPending
                ? now()->subMinutes(105 - $i)
                : now()
                    ->subDays(($i - 1) % 21)
                    ->subHours($i % 12);

            DB::transaction(function () use (
                $i,
                $orderNumber,
                $createdAt,
                $isPending,
                $isCancelled,
                $products,
                $inventoryService
            ) {
                $selected = [];

                for ($j = 0; $j < 3; $j++) {
                    $index = (($i * 7) + ($j * 11)) % count($products);
                    $product = $products[$index];

                    $selected[] = [
                        'product' => $product,
                        'quantity' => (($i + $j) % 3) + 1,
                    ];
                }

                $subtotal = 0;

                foreach ($selected as $entry) {
                    $subtotal += round(
                        (float) $entry['product']->price
                        * $entry['quantity'],
                        2
                    );
                }

                $shippingFee = 80;
                $total = $subtotal + $shippingFee;

                $status = $isPending
                    ? 'pending'
                    : ($isCancelled ? 'cancelled' : 'confirmed');

                $paymentStatus = $isPending || $isCancelled
                    ? 'pending'
                    : 'paid';

                $order = Order::create([
                    'order_number' => $orderNumber,
                    'customer_name' => 'Demo Customer '.$i,
                    'customer_email' => "customer{$i}@example.com",
                    'customer_phone' => '01700000000',
                    'shipping_address' => 'Dhaka, Bangladesh',
                    'subtotal' => number_format($subtotal, 2, '.', ''),
                    'shipping_fee' => '80.00',
                    'total' => number_format($total, 2, '.', ''),
                    'status' => $status,
                    'payment_status' => $paymentStatus,
                    'delivery_status' => 'pending',
                    'paid_at' => $paymentStatus === 'paid'
                        ? $createdAt
                        : null,
                    'cancelled_at' => $isCancelled
                        ? $createdAt
                        : null,
                    'expires_at' => $isPending
                        ? now()->addMinutes(30)
                        : null,
                ]);

                $order->timestamps = false;
                $order->created_at = $createdAt;
                $order->updated_at = $createdAt;
                $order->save();
                $order->timestamps = true;

                foreach ($selected as $entry) {
                    $product = $entry['product'];
                    $quantity = $entry['quantity'];
                    $unitPrice = (float) $product->price;

                    $order->items()->create([
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'quantity' => $quantity,
                        'unit_price' => number_format(
                            $unitPrice,
                            2,
                            '.',
                            ''
                        ),
                        'total_price' => number_format(
                            $unitPrice * $quantity,
                            2,
                            '.',
                            ''
                        ),
                    ]);

                    if (! $isCancelled) {
                        $inventoryService->adjustStock(
                            $product->id,
                            -$quantity,
                            'Assessment demo order '.$orderNumber,
                            'sale',
                            Order::class,
                            $order->id
                        );
                    }
                }

                if ($paymentStatus === 'paid') {
                    $payment = $order->payments()->create([
                        'gateway' => 'sslcommerz',
                        'transaction_id' => sprintf(
                            'DEMO-PAY-%04d',
                            $i
                        ),
                        'amount' => number_format(
                            $total,
                            2,
                            '.',
                            ''
                        ),
                        'currency' => 'BDT',
                        'status' => 'paid',
                        'metadata' => [
                            'demo' => true,
                            'source' => 'AssessmentSeeder',
                            'real_gateway_transaction' => false,
                        ],
                    ]);

                    $payment->timestamps = false;
                    $payment->created_at = $createdAt;
                    $payment->updated_at = $createdAt;
                    $payment->save();
                    $payment->timestamps = true;
                }
            }, 3);
        }

        $this->command?->info(
            'Assessment demo seeding completed.'
        );
    }
}
