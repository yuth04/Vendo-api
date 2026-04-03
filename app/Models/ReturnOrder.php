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
        'completed_at'
    ];

    protected $attributes = [
        'status' => 'pending',
        'refund_status' => 'pending',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ✅ Status constants
    const STATUS_PENDING = 'pending';
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
                    self::STATUS_PENDING => [self::STATUS_APPROVED, self::STATUS_REJECTED],
                    self::STATUS_APPROVED => [self::STATUS_PROCESSING],
                    self::STATUS_PROCESSING => [self::STATUS_COMPLETED],
                ];

                if (isset($allowed[$current]) && !in_array($next, $allowed[$current])) {
                    throw new \Exception("Invalid status transition: $current → $next");
                }

                if ($next === self::STATUS_APPROVED) {
<<<<<<< Updated upstream
                    $returnOrder->approved_at = now()->setTimezone('Asia/Phnom_Penh')->format('Y-m-d h:i A');
                }

                if ($next === self::STATUS_REJECTED) {
                    $returnOrder->rejected_at = now()->setTimezone('Asia/Phnom_Penh')->format('Y-m-d h:i A');
                }

                if ($next === self::STATUS_COMPLETED) {
                    $returnOrder->completed_at = now()->setTimezone('Asia/Phnom_Penh')->format('Y-m-d h:i A');
=======
                    $returnOrder->approved_at = now()->format('h:i d-m-Y');
                }

                if ($next === self::STATUS_REJECTED) {
                    $returnOrder->rejected_at = now()->format('h:i d-m-Y');
                }

                if ($next === self::STATUS_COMPLETED) {
                    $returnOrder->completed_at = now()->format('h:i d-m-Y');
>>>>>>> Stashed changes
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

    public function images()
    {
        return $this->hasMany(ReturnImage::class);
    }
}
