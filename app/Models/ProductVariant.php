<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'size', 'color', 'stock', 'status','sku'];
    
    protected $hidden = ['created_at', 'updated_at'];
        protected $casts = [
        'created_at' => 'datetime:d-m-Y g:i:s A',
        'updated_at' => 'datetime:d-m-Y g:i:s A',
        'product_id' => 'integer',
        'size' => 'string',
        'color' => 'string',
        'stock' => 'integer',
    ];
    
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function images()
    {
        return $this->hasMany(ProductImage::class, 'variant_id');
    }
    public function cartItems()
    {
        return $this->hasMany(CartItem::class, 'product_variant_id');
    }
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
    
}
