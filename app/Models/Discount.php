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
    protected $hidden = ['created_at', 'updated_at', 'discount_price', 'product_id'];
    protected $casts = [
        'created_at' => 'datetime:d-m-Y h:i:s A',
        'updated_at' => 'datetime:d-m-Y h:i:s A',
    ];
    public function discountCategory()
    {
        return $this->belongsTo(DiscountCategory::class, 'discount_category_id');
    }
    public function products()
    {
        return $this->hasMany(Product::class);
    }




}
