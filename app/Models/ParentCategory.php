<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParentCategory extends Model
{
    protected $fillable = ['name'];
    protected $hidden = ['create_at', 'update_at'];
    public function categories()
    {
        return $this->hasMany(Category::class);
    }

}
