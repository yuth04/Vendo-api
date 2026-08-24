<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Roles
        $adminRole = Role::updateOrCreate(['slug' => 'admin'], [
            'name' => 'Administrator',
            'description' => 'Full administrative access across the system.',
        ]);

        $staffRole = Role::updateOrCreate(['slug' => 'staff'], [
            'name' => 'Staff',
            'description' => 'Internal team member with management capabilities.',
        ]);

        $customerRole = Role::updateOrCreate(['slug' => 'customer'], [
            'name' => 'Customer',
            'description' => 'Standard customer account access.',
        ]);

        // 2. Attach Permissions to Roles
        $allPermissions = Permission::all();
        $staffPermissions = Permission::whereIn('slug', ['users.view', 'reports.view', 'profile.edit'])->get();
        $customerPermissions = Permission::whereIn('slug', ['profile.edit'])->get();

        $adminRole->permissions()->sync($allPermissions->pluck('id'));
        $staffRole->permissions()->sync($staffPermissions->pluck('id'));
        $customerRole->permissions()->sync($customerPermissions->pluck('id'));
    }
}