<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReviewReplied implements ShouldBroadcast
{
    use SerializesModels;

    public $reply;

    public function __construct($reply)
    {
        $this->reply = $reply;
    }

    public function broadcastOn()
    {
        return new Channel('reviews.' . $this->reply->parent_id);
    }

    public function broadcastAs()
    {
        return 'review.replied';
    }
}
