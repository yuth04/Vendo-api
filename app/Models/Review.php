<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = ['user_id', 'product_id', 'rating', 'comment', 'is_approved', 'image', 'image_file_id', 'parent_id'];
    protected $hidden = ['created_at', 'updated_at'];
    protected $casts = [
        'created_at' => 'datetime:d-m-Y g:i:s A',
        'updated_at' => 'datetime:d-m-Y g:i:s A',
    ];
    protected $appends = ['created_at_human'];
    public function getCreatedAtHumanAttribute()
    {
        return $this->created_at?->diffForHumans();
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function parent()
    {
        return $this->belongsTo(Review::class, 'parent_id');
    }
    public function replies()
    {
        return $this->hasMany(Review::class, 'parent_id');
    }

}
