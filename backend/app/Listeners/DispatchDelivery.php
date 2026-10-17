<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Jobs\DispatchCarryBeeDelivery;

class DispatchDelivery
{
    public function handle(OrderPaid $event): void
    {
        DispatchCarryBeeDelivery::dispatch($event->orderId)
            ->afterCommit();
    }
}
