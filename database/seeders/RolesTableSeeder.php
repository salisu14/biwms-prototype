<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesTableSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('roles', 'id'), COALESCE((SELECT MAX(id) FROM roles), 1), true)");
        }

        $roles = [
            ['name' => 'super_admin', 'guard_name' => 'web'],
            ['name' => 'admin', 'guard_name' => 'web'],
            ['name' => 'sales-representative', 'guard_name' => 'web'],
            ['name' => 'sales-manager', 'guard_name' => 'web'],
            ['name' => 'finance-accountant', 'guard_name' => 'web'],
            ['name' => 'finance-manager', 'guard_name' => 'web'],
            ['name' => 'warehouse-worker', 'guard_name' => 'web'],
            ['name' => 'warehouse-manager', 'guard_name' => 'web'],
            ['name' => 'factory-operator', 'guard_name' => 'web'],
            ['name' => 'factory-manager', 'guard_name' => 'web'],
            ['name' => 'hr-officer', 'guard_name' => 'web'],
            ['name' => 'hr-manager', 'guard_name' => 'web'],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                [
                    'name' => $role['name'],
                    'guard_name' => $role['guard_name'],
                ],
                []
            );
        }
    }
}
