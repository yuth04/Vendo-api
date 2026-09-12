<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReturnStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $returnId,
        public string $status,
        public string $message
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('returns'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'return.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'return_id' => $this->returnId,
            'return_status' => $this->status,
            'message' => $this->message,
        ];
    }
}