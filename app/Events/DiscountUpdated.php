<?php

namespace App\Events;

use App\Models\Discount;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiscountUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $action;
    public ?Discount $discount;
    public ?int $discountId;

    public function __construct(
        string $action,
        ?Discount $discount = null,
        ?int $discountId = null
    ) {
        $this->action = $action;
        $this->discount = $discount;
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
        return 'discount.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'discount' => $this->discount,
            'discountId' => $this->discountId,
        ];
    }
}