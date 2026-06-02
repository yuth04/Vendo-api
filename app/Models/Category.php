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
        'parent_category_id',
        'image_file_id',
    ];
    protected $hidden = ['created_at', 'updated_at'];
    protected $casts = [
        'created_at' => 'datetime:d-m-Y g:i:s A',
        'updated_at' => 'datetime:d-m-Y g:i:s A',
    ];
    // public function parentCategory()
    // {
    //     return $this->belongsTo(Category::class, 'parent_category_id');
    // }
    public function parentCategory()
    {
        return $this->belongsTo(ParentCategory::class,'parent_category_id'
        );
    }
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_category_id');
    }
    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }
    // public function brand(){
    //     return $this->belongsTo(Brand::class);
    // }

}
