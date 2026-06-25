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
        'discount_id',
        'status',
        'created_by',
        'updated_by'
    ];
    protected $casts = [
        'created_at' => 'datetime:d-m-Y g:i:s A',
        'updated_at' => 'datetime:d-m-Y g:i:s A',
        'updated_by' => 'array',
        'created_by' => 'array',
    ];
    protected $hidden = ['pivot', 'created_at', 'updated_at', 'discount_id'];
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
        return $this->belongsTo(Discount::class);
    }
    public function getFinalPrice()
    {
        $price = (float) $this->price;

        if ($this->discount_price && $this->discount_price < $price) {
            return (float) $this->discount_price;
        }

        return $price;
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
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function discounts()
    {
        return $this->belongsToMany(
            Discount::class,
            'discount_items'
        );
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
