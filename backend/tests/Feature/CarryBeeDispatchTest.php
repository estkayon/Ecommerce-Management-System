<?php

namespace Tests\Feature;

use App\Events\OrderPaid;
use App\Jobs\DispatchCarryBeeDelivery;
use App\Listeners\DispatchDelivery;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CarryBeeDispatchTest extends TestCase
{
    public function test_paid_order_event_queues_delivery_job(): void
    {
        Queue::fake();

        app(DispatchDelivery::class)->handle(
            new OrderPaid(123)
        );

        Queue::assertPushed(
            DispatchCarryBeeDelivery::class,
            fn (DispatchCarryBeeDelivery $job) =>
                $job->orderId === 123
        );

        Queue::assertPushed(
            DispatchCarryBeeDelivery::class,
            1
        );
    }
}
