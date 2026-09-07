<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnItem extends Model
{
    protected $fillable = [
        'return_order_id',
        'order_item_id',
        'quantity',
        'reason',
        'condition',
        'restocked',
        'return_type',
        'replacement_variant_id',
        'exchange_quantity',
        'exchange_price_difference',
    ];

    public const TYPE_REFUND = 'refund';
    public const TYPE_EXCHANGE = 'exchange';

    protected $casts = [
        'quantity' => 'integer',
        'exchange_quantity' => 'integer',
        'restocked' => 'boolean',
        'exchange_price_difference' => 'decimal:2',
        'created_at' => 'datetime:d-m-Y g:i:s A',
        'updated_at' => 'datetime:d-m-Y g:i:s A',
    ];

    protected $hidden = [
        'reason',
        // 'exchange',
        // 'refund'
    ];

    public function returnOrder()
    {
        return $this->belongsTo(
            ReturnOrder::class,
            'return_order_id'
        );
    }

    public function orderItem()
    {
        return $this->belongsTo(
            OrderItem::class,
            'order_item_id'
        );
    }

    public function replacementVariant()
    {
        return $this->belongsTo(
            ProductVariant::class,
            'replacement_variant_id'
        );
    }

    public function refund()
    {
        return $this->hasOne(
            Refund::class,
            'return_item_id'
        );
    }

    public function exchange()
    {
        return $this->hasOne(
            Exchange::class,
            'return_item_id'
        );
    }
}