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


}
