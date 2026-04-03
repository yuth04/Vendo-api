<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountCategory extends Model
{
    protected $fillable = [
        'title',
        'description',
        'is_active',
        'banner_image',
        'start_date',
        'end_date',
        'image_file_id',
        
    ];

  protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];
    protected $hidden = ['created_at', 'updated_at'];
    public function discounts()
    {
        return $this->hasMany(Discount::class);
    }
}
