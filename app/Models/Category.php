<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    //
    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'image',
        'parent_category_id'
    ];
      protected $hidden = ['create_at','update_at'];
    public function products(){
        return $this->hasMany(Product::class);
    }
    // public function brand(){
    //     return $this->belongsTo(Brand::class);
    // }
    public function parentCategory(){
        return $this->belongsTo(ParentCategory::class);
    }
}
