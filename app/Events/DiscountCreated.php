<?php

namespace App\Events;

use App\Models\Discount;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiscountCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $discount;

    public function __construct(Discount $discount)
    {
        $this->discount = $discount->load('products');
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('discounts'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'discount.created';
    }
}