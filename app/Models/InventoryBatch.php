<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryBatch extends Model
{
    protected $fillable = [
        'product_variant_id',
        'quantity',
        'remaining_quantity',
        'unit_cost',
        'stock_in_date',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'remaining_quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'stock_in_date' => 'datetime',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
            'product_variant_id'
        );
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(
            OrderItemInventoryBatch::class,
            'inventory_batch_id'
        );
    }

    public function getAgeInMonthsAttribute(): int
    {
        return $this->stock_in_date->diffInMonths(now());
    }

    public function getIsAgingAttribute(): bool
    {
        return $this->remaining_quantity > 0
            && $this->stock_in_date->lte(now()->subMonths(6));
    }

    // public function getIsDeadStockAttribute(): bool
    // {
    //     return $this->remaining_quantity > 0
    //         && $this->stock_in_date->lte(now()->subMonths(12));
    // }
    public function getStockAgeStatusAttribute(): string
    {
        if ($this->remaining_quantity <= 0) {
            return 'sold_out';
        }

        $months = $this->stock_in_date->diffInMonths(now());

        return match (true) {
            $months >= 12 => 'dead_stock',
            $months >= 9 => 'old_stock',
            $months >= 6 => 'slow moving',
            default => 'normal',
        };
    }

}