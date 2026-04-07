<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
class Discount extends Model
{

    protected $fillable = [
        'product_id',
        'name',
        'description',
        'amount',
        'type',
        'start_date',
        'end_date',
        'discount_category_id'
    ];
    protected $hidden = ['created_at', 'updated_at', 'discount_price'];
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function discountCategory()
    {
        return $this->belongsTo(DiscountCategory::class, 'discount_category_id');
    }

    // public function scopeActive($query)
    // {
    //     $now = now(); // Gets current date and time

    //     return $query->where('is_active', true)
    //         ->where(function ($q) use ($now) {
    //             $q->whereNull('start_date')
    //                 ->orWhere('start_date', '<=', $now);
    //         })
    //         ->where(function ($q) use ($now) {
    //             $q->whereNull('end_date')
    //                 ->orWhere('end_date', '>=', $now);
    //         });
    // }
}
