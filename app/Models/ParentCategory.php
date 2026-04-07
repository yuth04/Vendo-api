<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParentCategory extends Model
{
    protected $fillable = ['name','status'];
    protected $hidden = ['created_at', 'updated_at'];
    public function categories()
    {
        return $this->hasMany(Category::class);
    }
    public function products()
    {
        return $this->hasManyThrough(Product::class, Category::class);
    }
}
