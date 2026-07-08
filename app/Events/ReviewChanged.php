<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReviewChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $review;
    public $action;

    public function __construct($review, $action)
    {
        $this->review = $review;
        $this->action = $action;
    }

    public function broadcastOn()
    {
        return new Channel('reviews.' . $this->review->product_id);
    }

    public function broadcastAs()
    {
        return 'review.changed';
    }

    public function broadcastWith()
    {
        return [
            'action' => $this->action,
            'review' => [
                'id' => $this->review->id,
                'user_id' => $this->review->user_id,
                'product_id' => $this->review->product_id,
                'comment' => $this->review->comment,
                'parent_id' => $this->review->parent_id,
            ]
        ];
    }
}