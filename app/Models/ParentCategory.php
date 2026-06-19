<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParentCategory extends Model
{
    protected $fillable = ['name', 'status','slug'];

    protected $casts = [
        'created_at' => 'datetime:d-m-Y g:i:s A',
        'updated_at' => 'datetime:d-m-Y g:i:s A',
    ];
    protected $hidden = ['created_at', 'updated_at'];
    // public function categories()
    // {
    //     return $this->hasMany(Category::class);
    // }
    public function categories()
    {
        return $this->hasMany(Category::class,'parent_category_id'
        );
    }
    public function products()
    {
        return $this->hasManyThrough(Product::class, Category::class);
    }
}
