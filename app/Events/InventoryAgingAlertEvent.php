<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryAgingAlertEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $item;
    public int $userId;

    public function __construct(array $item, int $userId)
    {
        $this->item = $item;
        $this->userId = $userId;
    }

    public function broadcastOn(): array
    {
        // Broadcast to a private channel for the specific user/admin
        return [
            new PrivateChannel('admin-notifications.' . $this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'inventory.aging.alert';
    }
}