<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{

    protected $fillable = ['product_id', 'name', 'description', 'amount', 'type'];
    protected $hidden = ['create_at', 'update_at'];
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
