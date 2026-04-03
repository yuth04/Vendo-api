<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountCategory extends Model
{
    protected $fillable = ['title', 'description', 'banner_image', 'is_active', 'image_file_id', 'start_date', 'end_date'];
    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => "boolean"
    ];
    public function discounts()
    {
        return $this->hasMany(Discount::class);
    }
}
