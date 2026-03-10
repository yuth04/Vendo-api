<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $fillable = [
        'variant_id',
        'image',
        'position',
        'is_primary',
        'alt_text',
        'image_file_id'
    ];

    public function vairant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
