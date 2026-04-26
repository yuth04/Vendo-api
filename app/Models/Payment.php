<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['order_id', 'payment_method', 'amount', 'transaction_id', 'payment_reference', 'status', 'paid_at', 'refunded_at'];
    protected $casts = [
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime:d-m-Y h:i:s A',
        'created_at' => 'datetime:d-m-Y h:i:s A',
        'updated_at' => 'datetime:d-m-Y h:i:s A',
    ];
    
    protected $hidden = ['created_at', 'updated_at'];
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
