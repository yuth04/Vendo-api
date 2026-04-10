<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnImage extends Model
{
    protected $fillable = [
        'return_order_id',
        'image_path'
    ];
    protected $casts = [
        'created_at' => 'datetime:d-m-Y',
        'updated_at' => 'datetime:d-m-Y',
    ];
    public function returnOrder()
    {
        return $this->belongsTo(ReturnOrder::class);
    }
}
