<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnItem extends Model
{
    protected $fillable = [
        'return_order_id',
        'order_item_id',
        'quantity',
        'reason'
    ];
    public function returnOrder()
    {
        return $this->belongsTo(ReturnOrder::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
}
