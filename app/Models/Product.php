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
        'discount_price',
        'brand_id',
        'image',
        'image_file_id',
    ];

    protected $hidden = ['created_at', 'updated_at'];
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
        $now = now();
        return $this->hasOne(Discount::class)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $now);
            });
    }

}
