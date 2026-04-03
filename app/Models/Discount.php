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
    public function product()
    {
        return $this->belongsTo(Product::class, 'discount_category_id');
    }
    public function discountCategory()
    {
        return $this->belongsTo(DiscountCategory::class);
    }
    public function scopeActive(Builder $query)
    {
        return $query->where(function ($q) {
            $q->whereNull('start_date')
                ->orWhere('start_date', '<=', now());
        })->where(function ($q) {
            $q->whereNull('end_date')
                ->orWhere('end_date', '>=', now());
        });
    }
    // Optional: check if single discount is active
    public function getIsActiveAttribute()
    {
        $now = now('Asia/Phnom_Penh')->format('H:i d-m-Y');

        return (
            (is_null($this->start_date) || $this->start_date <= $now) &&
            (is_null($this->end_date) || $this->end_date >= $now)
        );
    }
    public function discountCategory()
    {
        return $this->belongsTo(DiscountCategory::class, 'discount_category_id');
    }
    public function scopeActive(Builder $query)
    {
        return $query->where(function ($q) {
            $q->whereNull('start_date')
                ->orWhere('start_date', '<=', now());
        })->where(function ($q) {
            $q->whereNull('end_date')
                ->orWhere('end_date', '>=', now());
        });
    }
    // Optional: check if single discount is active
    public function getIsActiveAttribute()
    {
        $now = now('Asia/Phnom_Penh')->format('H:i d-m-Y');

        return (
            (is_null($this->start_date) || $this->start_date <= $now) &&
            (is_null($this->end_date) || $this->end_date >= $now)
        );
    }
}
