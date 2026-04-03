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
        'discount_price',
        'image',
        'image_file_id',
    ];

    protected $hidden = ['created_at', 'updated_at', 'activeDiscount'];
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
        return $this->hasOne(Discount::class)
            ->where('product_id', $this->id)
            ->where(function ($query) {
                $now = now()->toDateTimeString(); // Uses Asia/Phnom_Penh
                $query->where('start_date', '<=', $now)
                    ->where('end_date', '>=', $now);
            });
    }

    // public function getDiscountPriceAttribute()
    // {
    //     $discount = $this->activeDiscount;

    //     if (!$discount)
    //         return $this->price;

    //     if ($discount->type === 'percent') {
    //         return round($this->price - ($this->price * $discount->amount / 100), 2);
    //     }

    //     return max(0, $this->price - $discount->amount);
    // }

}
