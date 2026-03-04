<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_variant_id',
        'product_name',
        'price',
        'quantity',
        'size',
        'color',

    ];
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // Relation: belongs to a ProductVariant
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
