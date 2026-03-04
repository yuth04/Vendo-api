<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductVariantSeeder extends Seeder
{
    public function run()
    {
        $sizes = ['S', 'M', 'L', 'XL'];
        $colors = ['Red', 'Blue', 'Green', 'Black', 'White'];

        $products = DB::table('products')->pluck('id')->toArray();

        foreach ($products as $productId) {
            // Generate all combinations for this product
            $combinations = [];
            foreach ($sizes as $size) {
                foreach ($colors as $color) {
                    $combinations[] = ['size' => $size, 'color' => $color];
                }
            }

            // Shuffle and pick random number of variants (1-5) per product
            shuffle($combinations);
            $variantCount = rand(1, 5);
            $selectedVariants = array_slice($combinations, 0, $variantCount);

            foreach ($selectedVariants as $variant) {
                DB::table('product_variants')->insert([
                    'product_id' => $productId,
                    'size' => $variant['size'],
                    'color' => $variant['color'],
                    'stock' => rand(1, 50),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }
    }
}
