<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryBatch;
use App\Models\Product;
use Illuminate\Http\Request;

class InventoryController extends Controller
{

    public function slowMovingStock(Request $request)
    {
        $status = $request->query('status', 'slow_moving');

        $query = InventoryBatch::with([
            'productVariant.product'
        ])
            ->where('remaining_quantity', '>', 0);

        switch ($status) {

            // 6 to less than 9 months
            case 'slow_moving':
                $query->where(
                    'stock_in_date',
                    '<=',
                    now()->subMonths(6)
                )
                    ->where(
                        'stock_in_date',
                        '>',
                        now()->subMonths(9)
                    );
                break;

            // 9 to less than 12 months
            case 'old_stock':
                $query->where(
                    'stock_in_date',
                    '<=',
                    now()->subMonths(9)
                )
                    ->where(
                        'stock_in_date',
                        '>',
                        now()->subMonths(12)
                    );
                break;

            // 12 months or older
            case 'dead_stock':
                $query->where(
                    'stock_in_date',
                    '<=',
                    now()->subMonths(12)
                );
                break;

            default:
                return response()->json([
                    'message' => 'Invalid stock status.'
                ], 422);
        }

        $batches = $query
            ->orderBy('stock_in_date', 'asc')
            ->get();

        $data = $batches->map(function ($batch) {
            return [
                'id' => $batch->id,
                'product_variant_id' => $batch->product_variant_id,
                'quantity' => $batch->quantity,
                'remaining_quantity' => $batch->remaining_quantity,

                'unit_cost' => $batch->unit_cost,
                'stock_in_date' => $batch->stock_in_date
                    ? $batch->stock_in_date->format('d M Y, h:i A')
                    : null,
                'age_in_months' => $batch->age_in_months . " months",
                'stock_age_status' => $batch->stock_age_status,
                'reference_type' => $batch->reference_type,
                'reference_id' => $batch->reference_id,

                'product_variant' => $batch->productVariant,

            ];
        });

        return response()->json([
            'message' => 'Slow moving stock retrieved successfully.',
            'status' => $status,
            'count' => $data->count(),
            'data' => $data,
        ]);
    }

    /**
     * Get a single inventory batch.
     */
    public function show($id)
    {
        $batch = InventoryBatch::with([
            'productVariant.product',
            'allocations.orderItem.order'
        ])->findOrFail($id);

        return response()->json([
            'message' => 'Inventory batch retrieved successfully.',
            'data' => $batch,
        ]);
    }
    public function inventorySummary()
    {
        $batches = InventoryBatch::where('remaining_quantity', '>', 0)
            ->get();

        $summary = [
            'normal' => 0,
            'aging' => 0,
            'old_stock' => 0,
            'dead_stock' => 0,
            'total_remaining_stock' => 0,
        ];

        foreach ($batches as $batch) {

            $status = $batch->stock_age_status;

            if (isset($summary[$status])) {
                $summary[$status] += $batch->remaining_quantity;
            }

            $summary['total_remaining_stock'] += $batch->remaining_quantity;
        }

        return response()->json([
            'message' => 'Inventory summary retrieved successfully.',
            'data' => $summary,
        ]);
    }
    public function stockAlerts()
    {
        // Low stock: 1 - 5
        $lowStockProducts = Product::with([
            'variants' => function ($query) {
                $query->where('stock', '>', 0)
                    ->where('stock', '<=', 5);
            }
        ])
            ->whereHas('variants', function ($query) {
                $query->where('stock', '>', 0)
                    ->where('stock', '<=', 5);
            })
            ->get();

        // Out of stock: 0
        $outOfStockProducts = Product::with([
            'variants' => function ($query) {
                $query->where('stock', 0);
            }
        ])
            ->whereHas('variants', function ($query) {
                $query->where('stock', 0);
            })
            ->get();

        // Aging: 6 - 8 months
        $agingStock = InventoryBatch::with([
            'productVariant.product'
        ])
            ->where('remaining_quantity', '>', 0)
            ->where('stock_in_date', '<=', now()->subMonths(6))
            ->where('stock_in_date', '>', now()->subMonths(9))
            ->orderBy('stock_in_date', 'asc')
            ->get()
            ->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'product_variant_id' => $batch->product_variant_id,
                    'quantity' => $batch->quantity,
                    'remaining_quantity' => $batch->remaining_quantity,
                    'unit_cost' => $batch->unit_cost,
                    'stock_in_date' => $batch->stock_in_date,
                    'age_in_months' => $batch->age_in_months,
                    'stock_age_status' => $batch->stock_age_status,
                    'product_variant' => $batch->productVariant,
                ];
            });

        // Old stock: 9 - 11 months
        $oldStock = InventoryBatch::with([
            'productVariant.product'
        ])
            ->where('remaining_quantity', '>', 0)
            ->where('stock_in_date', '<=', now()->subMonths(9))
            ->where('stock_in_date', '>', now()->subMonths(12))
            ->orderBy('stock_in_date', 'asc')
            ->get()
            ->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'product_variant_id' => $batch->product_variant_id,
                    'quantity' => $batch->quantity,
                    'remaining_quantity' => $batch->remaining_quantity,
                    'unit_cost' => $batch->unit_cost,
                    'stock_in_date' => $batch->stock_in_date,
                    'age_in_months' => $batch->age_in_months,
                    'stock_age_status' => $batch->stock_age_status,
                    'product_variant' => $batch->productVariant,
                ];
            });

        // Dead stock: 12+ months
        $deadStock = InventoryBatch::with([
            'productVariant.product'
        ])
            ->where('remaining_quantity', '>', 0)
            ->where('stock_in_date', '<=', now()->subMonths(12))
            ->orderBy('stock_in_date', 'asc')
            ->get()
            ->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'product_variant_id' => $batch->product_variant_id,
                    'quantity' => $batch->quantity,
                    'remaining_quantity' => $batch->remaining_quantity,
                    'unit_cost' => $batch->unit_cost,
                    'stock_in_date' => $batch->stock_in_date,
                    'age_in_months' => $batch->age_in_months,
                    'stock_age_status' => $batch->stock_age_status,
                    'product_variant' => $batch->productVariant,
                ];
            });

        return response()->json([
            'message' => 'Stock alerts retrieved successfully.',

            'data' => [
                'low_stock' => $lowStockProducts,
                'out_of_stock' => $outOfStockProducts,

                'aging_stock' => $agingStock,
                'old_stock' => $oldStock,
                'dead_stock' => $deadStock,
            ],
        ]);
    }

}