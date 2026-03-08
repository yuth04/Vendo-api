<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $fillable = [
        'variant_id',
        'image',
        'position'
    ];

    public function vairant(){
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
