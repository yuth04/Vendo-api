<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['order_id', 'payment_method', 'amount', 'transaction_id', 'payment_reference', 'status', 'paid_at'];
    protected $casts = [
        'paid_at' => 'datetime',  
        'created_at' => 'datetime:d-m-Y',
        'updated_at' => 'datetime:d-m-Y',
    ];
        protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
    protected $hidden = ['created_at', 'updated_at'];
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
