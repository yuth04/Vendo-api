<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Carousel extends Model
{
    protected $fillable = [
        'title',
        'description',
        'image',
        'image_file_id',
        'link',
        'position',
        'status'
    ];
      protected $hidden = ['created_at','updated_at'];
}
