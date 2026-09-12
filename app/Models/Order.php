<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'address_id',
        'order_number',
        'original_price',
        'sub_total_price',
        'total_price',
        'status',
        'contact',
        'note',
        'delivered_at',
        'shipped_at',
        'cancelled_at',
        'returned_at',
        'shipping_fee',
        'completed_at',
        'discount_amount',
        'coupon_code',
        'coupon_discount',
        'payment_transaction_id'
    ];
    protected $casts = [
        'delivered_at' => 'datetime:d-m-Y g:i:s A',
        'shipped_at' => 'datetime:d-m-Y g:i:s A',
        'cancelled_at' => 'datetime:d-m-Y g:i:s A',
        'returned_at' => 'datetime:d-m-Y g:i:s A',
        'completed_at' => 'datetime:d-m-Y g:i:s A',
        'created_at' => 'datetime:d-m-Y g:i:s A',
        'updated_at' => 'datetime:d-m-Y g:i:s A'
    ];
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('d-m-Y h:i:s A');
    }
    protected $hidden = ['updated_at'];
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_RETURNED = 'returned';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_SHIPPING = 'shipping';
    const STATUS_COMPLETED = 'completed';

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
    public function returnOrder()
    {
        return $this->hasOne(ReturnOrder::class);
    }
    public function latestPayment()
    {
        return $this->hasOne(Payment::class)->latest();
    }
    public function address()
    {
        return $this->belongsTo(Address::class, 'address_id');
    }
    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }
    public function getTimeAgoAttribute()
    {
        return Carbon::parse($this->created_at)->diffForHumans([
            'parts' => 2,
            'short' => true,
            'syntax' => Carbon::DIFF_RELATIVE_TO_NOW,
        ]);
    }
    public function returnItems()
    {
        return $this->hasMany(
            ReturnItem::class,
            'order_item_id'
        );
    }
}
