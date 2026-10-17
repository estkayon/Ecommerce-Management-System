<?php

namespace Tests\Feature;

use App\Jobs\DispatchCarryBeeDelivery;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarryBeeDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_order_creates_one_mock_delivery(): void
    {
        config()->set('services.carrybee.enabled', true);
        config()->set('services.carrybee.mode', 'mock');

        $order = Order::create([
            'order_number' => 'ORD-CB-001',
            'customer_name' => 'Test Customer',
            'customer_email' => 'customer@example.com',
            'customer_phone' => '01712345678',
            'shipping_address' => 'Dhaka, Bangladesh',
            'subtotal' => '2500.00',
            'shipping_fee' => '0.00',
            'total' => '2500.00',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'delivery_status' => 'pending',
            'paid_at' => now(),
        ]);

        $job = new DispatchCarryBeeDelivery($order->id);

        $job->handle(app(\App\Services\CarryBeeService::class));
        $job->handle(app(\App\Services\CarryBeeService::class));

        $this->assertDatabaseCount('deliveries', 1);

        $this->assertDatabaseHas('deliveries', [
            'order_id' => $order->id,
            'status' => 'created',
            'consignment_id' => 'MOCK-CB-'.$order->id,
            'tracking_number' => 'MOCK-TRK-'.$order->id,
            'attempts' => 1,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'delivery_status' => 'created',
        ]);
    }

    
public function test_unpaid_order_cannot_create_delivery(): void
{
    config()->set('services.carrybee.enabled', true);
    config()->set('services.carrybee.mode', 'mock');

    $order = Order::create([
        'order_number' => 'ORD-CB-002',
        'customer_name' => 'Unpaid Customer',
        'customer_email' => 'unpaid@example.com',
        'customer_phone' => '01712345678',
        'shipping_address' => 'Dhaka, Bangladesh',
        'subtotal' => '1500.00',
        'shipping_fee' => '0.00',
        'total' => '1500.00',
        'status' => 'pending',
        'payment_status' => 'pending',
        'delivery_status' => 'pending',
    ]);

    $job = new DispatchCarryBeeDelivery($order->id);

    $job->handle(app(\App\Services\CarryBeeService::class));

    $this->assertDatabaseCount('deliveries', 0);
}

}
