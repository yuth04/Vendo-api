<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductDamage extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'reason',
        'quantity',
        'status',
        'image',
        'image_file_id'
    ];
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
