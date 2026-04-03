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
        'image_file_id',
        'discount_price'
    ];

    protected $hidden = ['created_at', 'updated_at','activeDiscount'];

    // protected $appends = ['discount_price'];

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

    public function discount()
    {
        return $this->hasOne(Discount::class);
    }

    public function activeDiscount()
    {
        return $this->hasOne(Discount::class)->latest();
    }


}
