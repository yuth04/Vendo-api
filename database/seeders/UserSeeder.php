<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create a specific user you can use to log in
        User::create([
            'first_name' => 'Nheung',
            'last_name' => 'Phearakyuth',
            'email' => 'yuth.code@gmail.com',
            'password' => Hash::make('admin@123'), 
        ]);

        User::factory()->count(1)->create();
    }
}