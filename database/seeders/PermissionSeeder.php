<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // User Management
            ['name' => 'View Users',   'slug' => 'users.view',   'description' => 'Can view user list and profiles'],
            ['name' => 'Create Users', 'slug' => 'users.create', 'description' => 'Can create new users'],
            ['name' => 'Edit Users',   'slug' => 'users.edit',   'description' => 'Can edit existing user accounts'],
            ['name' => 'Delete Users', 'slug' => 'users.delete', 'description' => 'Can delete user accounts'],

            // Reports & Analytics
            ['name' => 'View Reports', 'slug' => 'reports.view', 'description' => 'Can view business reports'],

            // Customer Portal / General
            ['name' => 'Edit Profile', 'slug' => 'profile.edit', 'description' => 'Can edit own personal profile'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
        }
    }
}