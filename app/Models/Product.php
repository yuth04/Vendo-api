<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscountCategory extends Model
{
    protected $fillable = [
        'title', 
        'description', 
        'banner_image', 
        'is_active', 
        'image_file_id', 
        'start_date', 
        'end_date'
    ];


    protected $hidden = ['created_at', 'updated_at', 'activeDiscount'];
    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean'
    ];

    /**
     * Get the discounts associated with the category.
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class);
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



