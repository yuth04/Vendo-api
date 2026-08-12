<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountItem extends Model
{
      protected $fillable = [
        'discount_id',
        'product_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'pivot'
    ];

    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
