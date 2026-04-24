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
        'type'
    ];
        protected $casts = [
        'approved_at' => 'datetime:d-m-Y',
        'rejected_at' => 'datetime:d-m-Y',
        'completed_at' => 'datetime:d-m-Y',   
        'created_at' => 'datetime:d-m-Y',
        'updated_at' => 'datetime:d-m-Y',
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
        'user_id'
    ];

    // ✅ Status constants
    const STATUS_REQUESTED = 'requested';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';

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
                    self::STATUS_APPROVED => [self::STATUS_COMPLETED],
        
                ];

                if (isset($allowed[$current]) && !in_array($next, $allowed[$current])) {
                    throw new \Exception("Invalid status transition: $current → $next");
                }

                if ($next === self::STATUS_APPROVED) {
                    $returnOrder->approved_at = now();

                    $returnOrder->approved_at = now();

                }

                if ($next === self::STATUS_REJECTED) {
                    $returnOrder->rejected_at = now();
                }

                if ($next === self::STATUS_COMPLETED) {

                    $returnOrder->completed_at = now();

                    $returnOrder->approved_at = now();
                }

                if ($next === self::STATUS_REJECTED) {
                    $returnOrder->rejected_at = now();
                }

                if ($next === self::STATUS_COMPLETED) {
                    $returnOrder->completed_at = now();

                    $returnOrder->completed_at = now();

                    $returnOrder->refund_status = self::REFUND_REFUNDED;

                    if (!$returnOrder->refund_amount) {
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



}
