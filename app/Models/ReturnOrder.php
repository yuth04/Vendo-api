<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnOrder extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'reason',
        'note',
        'status',
        'refund_amount',
        'refund_status',
        'approved_at',
        'rejected_at',
        'completed_at',
        'image',
        'refund_method',
        'admin_note',
        'received_at',
        'tracking_number',
        'carrier',
        'return_type'
    ];

    protected $casts = [
        'approved_at' => 'datetime:d-m-Y g:i:s A',
        'rejected_at' => 'datetime:d-m-Y g:i:s A',
        'completed_at' => 'datetime:d-m-Y g:i:s A',
        'created_at' => 'datetime:d-m-Y g:i:s A',
        'updated_at' => 'datetime:d-m-Y g:i:s A',
        'received_at' => 'datetime:d-m-Y g:i:s A',
    ];

    protected $attributes = [
        'status' => 'requested',
        'refund_status' => 'pending',
    ];

    protected $hidden = [
        'approved_at',
        'rejected_at',
        'completed_at',
        'updated_at',
        'user_id',
    ];

    // Status constants
    const STATUS_REQUESTED = 'requested';
    const STATUS_APPROVED = 'approved';
    const STATUS_RECEIVED = 'received';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const TYPE_REFUND = 'refund';
    const TYPE_EXCHANGE = 'exchange';
    const REFUND_PENDING = 'pending';
    const REFUND_REFUNDED = 'refunded';

    protected static function booted()
    {
        static::updating(function ($returnOrder) {
            if ($returnOrder->isDirty('status')) {

                $current = $returnOrder->getOriginal('status');
                $next = $returnOrder->status;

                $allowed = [
                    self::STATUS_REQUESTED => [self::STATUS_APPROVED, self::STATUS_REJECTED],
                    self::STATUS_APPROVED => [self::STATUS_RECEIVED, self::STATUS_COMPLETED],
                    self::STATUS_RECEIVED => [self::STATUS_COMPLETED],
                ];

                if (isset($allowed[$current]) && !in_array($next, $allowed[$current], true)) {
                    throw new \Exception("Invalid status transition: $current → $next");
                }

                // Handle Timestamps cleanly
                match ($next) {
                    self::STATUS_APPROVED => $returnOrder->approved_at = now(),
                    self::STATUS_RECEIVED => $returnOrder->received_at = now(),
                    self::STATUS_REJECTED => $returnOrder->rejected_at = now(),
                    self::STATUS_COMPLETED => $returnOrder->completed_at = now(),
                    default => null,
                };

                // Trigger refund logic ONLY when order moves to COMPLETED state
                if ($next === self::STATUS_COMPLETED && $returnOrder->return_type === self::TYPE_REFUND) {
                    $returnOrder->refund_status = self::REFUND_REFUNDED;

                    if (!$returnOrder->refund_amount && $returnOrder->relationLoaded('order')) {
                        $returnOrder->refund_amount = $returnOrder->order?->total;
                    } elseif (!$returnOrder->refund_amount && $returnOrder->order) {
                        $returnOrder->refund_amount = $returnOrder->order->total;
                    }
                }
            }
        });
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function items()
    {
        return $this->hasMany(ReturnItem::class);
    }

    public function returnImages()
    {
        return $this->hasMany(ReturnImage::class);
    }
    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

}