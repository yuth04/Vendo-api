<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'name',
        'code',
        'type',
        'value',
        'min_amount',
        'max_discount',
        'start_date',
        'end_date',
        'usage_limit',
        'used',
        'status',
        'image',
        'image_file_id',
        'description',
        'discount_id'
    ];
    protected $casts = [
        'created_at' => 'datetime:d-m-Y h:i:s A',
        'updated_at' => 'datetime:d-m-Y h:i:s A',
        'start_date' => 'datetime:d-m-Y h:i:s A',
        'end_date' => 'datetime:d-m-Y h:i:s A',
    ];
    protected $hidden = [
        'image_file_id'
    ];
    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }

    public function isValid($total)
    {
        if (!$this->status) {
            return false;
        }

        if ($this->end_date && now()->gt($this->end_date)) {
            return false;
        }

        if ($this->usage_limit && $this->used >= $this->usage_limit) {
            return false;
        }

        if ($this->min_amount && $total < $this->min_amount) {
            return false;
        }

        return true;
    }

    public function calculateDiscount($total)
    {
        if ($this->type === 'fixed') {
            return $this->value;
        }

        if ($this->type === 'percent') {
            $discount = ($total * $this->value) / 100;

            if ($this->max_discount) {
                return min($discount, $this->max_discount);
            }

            return $discount;
        }

        return 0;
    }
    // public function isValid($total)
    // {
    //     if (!$this->is_active)
    //         return false;

    //     if ($this->expires_at && now()->gt($this->expires_at))
    //         return false;

    //     if ($this->usage_limit && $this->used >= $this->usage_limit)
    //         return false;

    //     if ($this->min_amount && $total < $this->min_amount)
    //         return false;

    //     return true;
    // }

}
