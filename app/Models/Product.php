<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'productName',
        'slug',
        'description',
        'price',
        'category_id',
        'brand_id',
        'image',
    ];
    public function category()
    {
        return $this->belongsTo(Category::class);

    }
    public function brand()
    {
        return $this->belongsTo(Brand::class);

    }
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function discounts()
    {
        return $this->hasMany(Discount::class);

    }
    public function activeDiscount()
    {
        return $this->hasOne(Discount::class)->latest();
    }
}
