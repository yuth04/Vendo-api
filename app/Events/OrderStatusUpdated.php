<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    use SerializesModels;
    /**
     * Create a new event instance.
     */
    public function __construct(public int $orderId, public string $status,public string $paymentStatus = 'unpaid')
    {


    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('orders'),
        ];
    }
    public function broadcastWith()
    {
        return [
            'order_id' => $this->orderId,
            'status' => $this->status,
            'payment_status' => $this->paymentStatus,
        ];
    }
}
