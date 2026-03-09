<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $categories = ['T-Shirts', 'Jeans', 'Dresses', 'Shoes', 'Jackets', 'Hoodies'];
        $parentCategory = [
            1,
            2
        ];
        foreach ($parentCategory as $parentId) {
            foreach ($categories as $category) {
                DB::table('categories')->insert([
                    'name' => $category,
                    'parent_category_id'=>$parentId,
                    'slug' => \Str::slug($category),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }
    }
}
