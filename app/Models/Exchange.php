<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class Exchange extends Model
{
    protected $fillable = [
        'return_order_id',
        'return_item_id',
        'order_id',
        'user_id',
        'status',
        'price_difference',
        'tracking_number',
        'carrier',
        'processing_at',
        'shipped_at',
        'received_at',
        'failed_at',
        'failure_reason',
    ];

    protected $casts = [
        'price_difference' => 'decimal:2',
        'processing_at' => 'datetime',
        'shipped_at' => 'datetime',
        'received_at' => 'datetime',
        'failed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('d-m-Y g:i:s A');
    }
 
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public function returnItem()
    {
        return $this->belongsTo(
            ReturnItem::class,
            'return_item_id'
        );
    }

    public function returnOrder()
    {
        return $this->belongsTo(
            ReturnOrder::class,
            'return_order_id'
        );
    }

    public function order()
    {
        return $this->belongsTo(
            Order::class,
            'order_id'
        );
    }

    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }
}