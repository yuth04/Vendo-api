<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnOrder extends Model
{
    protected $fillable = [
        'order_id',
        'return_number',
        'user_id',
        // 'return_type',
        // 'reason',
        'note',
        'status',
        'approved_at',
        'rejected_at',
        'received_at',
        'inspected_at',
        'completed_at',
        'tracking_number',
        'carrier',
        'admin_note',
        'image',
    ];

    protected $casts = [
        'approved_at' => 'datetime:d-m-Y g:i:s A',
        'rejected_at' => 'datetime:d-m-Y g:i:s A',
        'received_at' => 'datetime:d-m-Y g:i:s A',
        'inspected_at' => 'datetime:d-m-Y g:i:s A',
        'completed_at' => 'datetime:d-m-Y g:i:s A',
        'created_at' => 'datetime:d-m-Y g:i:s A',
        'updated_at' => 'datetime:d-m-Y g:i:s A',
    ];
    public const TYPE_REFUND = 'refund';
    public const TYPE_EXCHANGE = 'exchange';

    protected $attributes = [
        'status' => self::STATUS_REQUESTED,
    ];

    protected $hidden = [
        'user_id',
        'updated_at',
    ];


    // Status
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_INSPECTED = 'inspected';
    public const STATUS_COMPLETED = 'completed';



    //  Relationships


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


}