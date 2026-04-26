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
    protected $casts = [
        'created_at' => 'datetime:d-m-Y h:i:s A',
        'updated_at' => 'datetime:d-m-Y h:i:s A',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
