<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    //
    protected $fillable = [
        'name',
        'slug',
    ];
    protected $hidden = ['create_at','update_at'];
    public function products(){
        return $this->hasMany(Product::class);
        
    }
    // public function categories(){
    //     return $this->hasMany(Category::class);
    // }
}
