<?php

namespace App\Console\Commands;

use App\Models\InventoryBatch;
use App\Models\ProductVariant;
use Illuminate\Console\Command;

class InitializeInventoryBatches extends Command
{
    protected $signature = 'inventory:initialize-batches';

    protected $description =
        'Create initial inventory batches from existing product variant stock';

    public function handle(): int
    {
        $variants = ProductVariant::query()
            ->where('stock', '>', 0)
            ->get();

        foreach ($variants as $variant) {

            $alreadyExists = InventoryBatch::where(
                'product_variant_id',
                $variant->id
            )->exists();

            if ($alreadyExists) {
                continue;
            }

            InventoryBatch::create([
                'product_variant_id' => $variant->id,
                'quantity' => $variant->stock,
                'remaining_quantity' => $variant->stock,
                'unit_cost' => null,
                'stock_in_date' => now(),
                'reference_type' => 'initial_stock',
                'reference_id' => null,
            ]);

            $this->info(
                "Created batch for variant {$variant->id}: {$variant->stock}"
            );
        }

        $this->info('Inventory initialization completed.');

        return self::SUCCESS;
    }
}