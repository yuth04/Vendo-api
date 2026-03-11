<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'size', 'color', 'price', 'stock'];
    protected $hidden = ['create_at', 'update_at'];
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function images()
    {
        return $this->hasMany(ProductImage::class, 'variant_id');
    }


}
