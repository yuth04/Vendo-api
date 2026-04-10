<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    //
    protected $fillable = [
        'name',
        'slug',
        'status',
        'image',
        'image_file_id',
    ];
    protected $hidden = ['created_at','updated_at'];
        protected $casts = [
        'created_at' => 'datetime:d-m-Y',
        'updated_at' => 'datetime:d-m-Y',
    ];
    public function products(){
        return $this->hasMany(Product::class);
        
    }
    // public function categories(){
    //     return $this->hasMany(Category::class);
    // }
}
