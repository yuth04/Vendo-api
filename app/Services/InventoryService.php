<?php

namespace App\Services;

use App\Models\InventoryBatch;
use App\Models\OrderItem;
use App\Models\OrderItemInventoryBatch;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryService
{
    /**
     * Add new stock.
     */
    public function addStock(
        ProductVariant $variant,
        int $quantity,
        ?float $unitCost = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): InventoryBatch {
        if ($quantity <= 0) {
            throw new RuntimeException(
                'Stock quantity must be greater than zero.'
            );
        }

        return DB::transaction(function () use ($variant, $quantity, $unitCost, $referenceType, $referenceId) {

            $variant = ProductVariant::where('id', $variant->id)
                ->lockForUpdate()
                ->firstOrFail();

            $batch = InventoryBatch::create([
                'product_variant_id' => $variant->id,
                'quantity' => $quantity,
                'remaining_quantity' => $quantity,
                'unit_cost' => $unitCost,
                'stock_in_date' => now(),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);

            $variant->increment('stock', $quantity);

            return $batch;
        });
    }

    /**
     * Deduct stock using FIFO.
     *
     * Returns the batches used.
     */
    public function deductStock(
        ProductVariant $variant,
        int $quantity,
        OrderItem $orderItem
    ): array {
        if ($quantity <= 0) {
            throw new RuntimeException(
                'Quantity must be greater than zero.'
            );
        }

        $remaining = $quantity;
        $allocations = [];

        /*
         * Lock the variant first.
         *
         * This prevents two checkout requests from
         * modifying the same variant simultaneously.
         */
        $variant = ProductVariant::where('id', $variant->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($variant->stock < $quantity) {
            throw new RuntimeException(
                "Insufficient stock for {$variant->product->productName}."
            );
        }

        /*
         * FIFO:
         *
         * oldest stock_in_date first
         */
        $batches = InventoryBatch::where(
            'product_variant_id',
            $variant->id
        )
            ->where('remaining_quantity', '>', 0)
            ->orderBy('stock_in_date', 'asc')
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {

            if ($remaining <= 0) {
                break;
            }

            $deductQuantity = min(
                $batch->remaining_quantity,
                $remaining
            );

            $batch->decrement(
                'remaining_quantity',
                $deductQuantity
            );

            $allocation = OrderItemInventoryBatch::create([
                'order_item_id' => $orderItem->id,
                'inventory_batch_id' => $batch->id,
                'quantity' => $deductQuantity,
                'unit_cost' => $batch->unit_cost,
            ]);

            $allocations[] = $allocation;

            $remaining -= $deductQuantity;
        }

        if ($remaining > 0) {
            throw new RuntimeException(
                "Inventory batches do not contain enough stock for {$variant->product->productName}."
            );
        }

        /*
         * Keep existing ProductVariant stock synchronized.
         */
        $variant->decrement('stock', $quantity);

        return $allocations;
    }

    /**
     * Release stock from an order.
     *
     * Useful when unpaid KHQR/payment expires
     * or an order is cancelled.
     */
    public function releaseOrderStock(OrderItem $orderItem): void
    {
        DB::transaction(function () use ($orderItem) {

            $allocations = OrderItemInventoryBatch::where(
                'order_item_id',
                $orderItem->id
            )
                ->lockForUpdate()
                ->get();

            if ($allocations->isEmpty()) {
                return;
            }

            $variant = ProductVariant::where(
                'id',
                $orderItem->product_variant_id
            )
                ->lockForUpdate()
                ->first();

            if (!$variant) {
                return;
            }

            foreach ($allocations as $allocation) {

                $batch = InventoryBatch::where(
                    'id',
                    $allocation->inventory_batch_id
                )
                    ->lockForUpdate()
                    ->first();

                if (!$batch) {
                    continue;
                }

                $batch->increment(
                    'remaining_quantity',
                    $allocation->quantity
                );

                $variant->increment(
                    'stock',
                    $allocation->quantity
                );
            }
        });
    }

    /**
     * Get aging stock.
     */
    public function getAgingStock(int $months = 6)
    {
        return InventoryBatch::with([
            'productVariant.product'
        ])
            ->where('remaining_quantity', '>', 0)
            ->where(
                'stock_in_date',
                '<=',
                now()->subMonths($months)
            )
            ->orderBy('stock_in_date', 'asc')
            ->get();
    }

    /**
     * Get dead stock.
     */
    public function getDeadStock(int $months = 12)
    {
        return InventoryBatch::with([
            'productVariant.product'
        ])
            ->where('remaining_quantity', '>', 0)
            ->where(
                'stock_in_date',
                '<=',
                now()->subMonths($months)
            )
            ->orderBy('stock_in_date', 'asc')
            ->get();
    }
}