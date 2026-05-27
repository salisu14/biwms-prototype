<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class IdentityAndAccessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Define Permissions
        $permissions = [
            // Trading
            'view:any:order', 'create:order', 'edit:order', 'delete:order', 'approve:order', 'post:order',
            // Inventory
            'view:any:item', 'create:item', 'edit:item', 'delete:item', 'adjust:inventory',
            // Finance
            'view:any:gl', 'post:journal',
            // HR & Setup
            'manage:users', 'manage:employees', 'manage:setup',
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // 2. Define baseline roles and assign permissions
        $superAdmin = Role::updateOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = Role::updateOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin->syncPermissions([
            'view:any:order', 'create:order', 'edit:order', 'approve:order', 'post:order',
            'view:any:item', 'create:item', 'edit:item', 'delete:item',
            'manage:users', 'manage:employees', 'manage:setup',
        ]);

        // 3. Assign Super Admin to first user
        $admin = User::first();
        if ($admin) {
            // Assign super_admin role
            $admin->assignRole($superAdmin);
        }
    }
}
