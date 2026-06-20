<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $orderId,
        public string $status,
        public string $paymentStatus = 'unpaid'
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('orders'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'order_id'       => $this->orderId,
            'order_status'   => $this->status,
            'payment_status' => $this->paymentStatus,
        ];
    }
}