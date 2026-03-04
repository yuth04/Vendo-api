<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = ['user_id'];
    protected $hidden = ['created_at', 'updated_at'];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }
    protected static function booted()
    {
        static::deleting(function ($cart) {
            $cart->items()->delete();
        });
    }

}
