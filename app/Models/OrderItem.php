<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_variant_id',
        'product_name',
        'product_image',
        'price',
        'quantity',
        'size',
        'color',

    ];
    protected $hidden = ['created_at', 'updated_at'];
    protected $casts = [
        'created_at' => 'datetime:d-m-Y g:i:s A',
        'updated_at' => 'datetime:d-m-Y g:i:s A',
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
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
    // public function productVariant()
    // {
    //     return $this->belongsTo(ProductVariant::class);
    // }
    
}
