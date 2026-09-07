<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnImage extends Model
{
    protected $fillable = ['return_order_id','url'];

    public function returnOrder(){
        return $this->belongsTo(ReturnOrder::class);
    }

}
