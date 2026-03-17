<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $hidden = ['created_at', 'updated_at'];
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'address_line',
        'city',
        'province',
        'postal_code',
        'phone',
        'is_default',
    ];
    public function user(){
        return $this->belongsTo(User::class);
    }
}
