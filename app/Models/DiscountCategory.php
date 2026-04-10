<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscountCategory extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'banner_image',
        'is_active',
        'image_file_id',
        'start_date',
        'end_date',

    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime:d-m-Y',
        'updated_at' => 'datetime:d-m-Y',


    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    /**
     * Get the discounts associated with the category.
     */
    public function discounts()
    {
        return $this->hasMany(Discount::class, 'discount_category_id');
    }
}