<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'address_id',
        'order_number',
        'total_price',
        'status',
        'contact',
        'note',
        'delivered_at',
        'shipped_at',
        'cancelled_at',
        'returned_at',
        'shipping_fee'

    ];
    protected $casts = [
        'delivered_at' => 'datetime',
        'shipped_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'returned_at' => 'datetime',
    ];
    protected $hidden = ['created_at', 'updated_at'];
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'paid';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';

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
    public function address()
    {
        return $this->belongsTo(Address::class, 'address_id');
    }
}
