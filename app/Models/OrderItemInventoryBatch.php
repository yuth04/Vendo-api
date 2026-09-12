<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemInventoryBatch extends Model
{
    protected $fillable = [
        'order_item_id',
        'inventory_batch_id',
        'quantity',
        'unit_cost',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(
            OrderItem::class,
            'order_item_id'
        );
    }

    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(
            InventoryBatch::class,
            'inventory_batch_id'
        );
    }
}