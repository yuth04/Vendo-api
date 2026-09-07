<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'return_order_id',
        'return_item_id',
        'payment_id',
        'user_id',
        'amount',
        'method',
        'provider',
        'status',
        'refund_reference',
        'processed_at',
        'failed_at',
        'failure_reason',
        'khqr_receiver_number',
        'khqr_account_name',
        'khqr_qr_code',
        'khqr_transaction_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime:d-m-Y g:i:s A',
        'failed_at' => 'datetime:d-m-Y g:i:s A',
        'created_at' => 'datetime:d-m-Y g:i:s A',
        'updated_at' => 'datetime:d-m-Y g:i:s A',
    ];

    protected $attributes = [
        'method' => 'khqr',
        'provider' => 'bakong',
        'status' => 'pending',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    /**
     * Prevent explicit null values from being set on 'method'
     */
    protected function method(): Attribute
    {
        return Attribute::make(
            set: fn($value) => $value ?? 'khqr'
        );
    }

    /**
     * Prevent explicit null values from being set on 'provider'
     */
    protected function provider(): Attribute
    {
        return Attribute::make(
            set: fn($value) => $value ?? 'bakong'
        );
    }

    // Relationships
    public function returnItem(): BelongsTo
    {
        return $this->belongsTo(ReturnItem::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}