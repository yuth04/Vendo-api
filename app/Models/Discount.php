<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
class Discount extends Model
{

    protected $fillable = [
        'name',
        'description',
        'amount',
        'type',
        'start_date',
        'end_date',
        'banner_image',
        'image_file_id',
        'slug',
        'is_active',
    ];
    protected $hidden = ['created_at', 'updated_at', 'discount_price','image_file_id'];
    protected $casts = [
        'created_at' => 'datetime:d-m-Y h:i:s A',
        'updated_at' => 'datetime:d-m-Y h:i:s A',
        'is_active'=>'boolean'
    ];
    public function discountCategory()
    {
        return $this->belongsTo(DiscountCategory::class, 'discount_category_id');
    }
    public function products()
    {
        return $this->hasMany(Product::class);
    }
    public function coupon(){
        return $this->hasOne(Coupon::class,'discount_id');
    }



}
