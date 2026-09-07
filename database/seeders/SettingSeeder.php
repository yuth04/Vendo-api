<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::insert([
            ['key' => 'store_name', 'value' => 'Vendo Store'],
            ['key' => 'store_description', 'value' => 'Online Shopping Platform'],
            ['key' => 'support_email', 'value' => 'admin@vendo.com'],
            ['key' => 'phone_number', 'value' => '+85512345678'],
            ['key' => 'address', 'value' => 'Phnom Penh, Cambodia'],
            ['key' => 'timezone', 'value' => 'Asia/Phnom_Penh'],
            ['key' => 'language', 'value' => 'en'],
            ['key' => 'currency', 'value' => 'USD'],
        ]);
    }
}
