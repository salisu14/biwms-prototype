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

        // 2. Define Roles and Assign Permissions

        // Super Admin
        $superAdmin = Role::updateOrCreate(['name' => 'SUPER_ADMIN', 'guard_name' => 'web']);

        // Trading Manager
        $tradingManager = Role::updateOrCreate(['name' => 'TRADING_MANAGER', 'guard_name' => 'web']);
        $tradingManager->syncPermissions([
            'view:any:order', 'create:order', 'edit:order', 'approve:order', 'post:order',
            'view:any:item', 'edit:item',
        ]);

        // Purchasing Agent
        $purchasingAgent = Role::updateOrCreate(['name' => 'PURCHASING_AGENT', 'guard_name' => 'web']);
        $purchasingAgent->syncPermissions([
            'view:any:order', 'create:order', 'edit:order',
            'view:any:item',
        ]);

        // Warehouse Manager
        $whseManager = Role::updateOrCreate(['name' => 'WAREHOUSE_MANAGER', 'guard_name' => 'web']);
        $whseManager->syncPermissions([
            'view:any:order',
            'view:any:item', 'adjust:inventory',
        ]);

        // 3. Assign Super Admin to first user
        $admin = User::first();
        if ($admin) {
            // Assign SUPER_ADMIN role
            $admin->assignRole($superAdmin);
        }

        // Cleanup legacy roles if they exist and are NOT the new ones
        Role::whereIn('name', ['admin', 'super_admin'])->delete();
    }
}
