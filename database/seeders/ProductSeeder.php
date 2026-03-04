<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Faker\Factory as Faker;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
        public function run()
    {
        $faker = Faker::create();

        // Get category and brand IDs
        $categories = DB::table('categories')->pluck('id')->toArray();
        $brands = DB::table('brands')->pluck('id')->toArray();

        for ($i = 1; $i <= 50; $i++) {
            $name = $faker->words(2, true);
            DB::table('products')->insert([
                'productName' => ucfirst($name),
                'slug' => \Str::slug($name),
                'description' => $faker->sentence(),
                'price' => $faker->numberBetween(10, 200),
                'category_id' => $faker->randomElement($categories),
                'brand_id' => $faker->randomElement($brands),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}
