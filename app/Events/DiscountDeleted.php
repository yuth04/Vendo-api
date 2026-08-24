<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiscountDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $discountId;

    public function __construct($discountId)
    {
        $this->discountId = $discountId;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('discounts'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'discount.deleted';
    }
}