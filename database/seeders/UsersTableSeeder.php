<?php

namespace Database\Seeders;

use App\Enums\EmployeeAssignmentType;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        $this->syncPostgresSequences();

        Role::query()
            ->where('guard_name', 'web')
            ->orderBy('id')
            ->get()
            ->each(function (Role $role, int $index): void {
                $employee = $this->employeeForRole($role, $index + 1);
                $user = $this->userForRole($role, $employee);

                $user->syncRoles([$role->name]);
            });
    }

    private function syncPostgresSequences(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['employees', 'users'] as $table) {
            DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 1), true)");
        }
    }

    private function employeeForRole(Role $role, int $sequence): Employee
    {
        [$firstName, $lastName] = $this->personNameForRole($role->name);

        return Employee::query()->updateOrCreate(
            ['employee_number' => sprintf('ROLE-%03d', $sequence)],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $this->emailForRole($role->name),
                'phone' => '+234800000'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
                'job_title' => $this->titleForRole($role->name),
                'assignment_type' => EmployeeAssignmentType::Corporate,
                'business_code' => null,
                'factory_code' => null,
                'department_code' => $this->departmentForRole($role->name),
                'is_active' => true,
            ],
        );
    }

    private function userForRole(Role $role, Employee $employee): User
    {
        return User::query()->updateOrCreate(
            ['email' => $this->emailForRole($role->name)],
            [
                'employee_id' => $employee->id,
                'name' => trim("{$employee->first_name} {$employee->last_name}"),
                'password' => Hash::make('password123@'),
                'email_verified_at' => now(),
                'remember_token' => null,
            ],
        );
    }

    private function emailForRole(string $roleName): string
    {
        if ($roleName === 'super_admin') {
            return 'sadmin@admin.com';
        }

        return str($roleName)->replace('_', '-')->lower().'@biwms.test';
    }

    private function titleForRole(string $roleName): string
    {
        return str($roleName)
            ->replace(['_', '-'], ' ')
            ->title()
            ->toString();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function personNameForRole(string $roleName): array
    {
        $title = $this->titleForRole($roleName);

        if ($roleName === 'super_admin') {
            return ['Super', 'Admin'];
        }

        $parts = explode(' ', $title);

        return [
            $parts[0] ?? 'BIWMS',
            implode(' ', array_slice($parts, 1)) ?: 'User',
        ];
    }

    private function departmentForRole(string $roleName): ?string
    {
        return match (true) {
            str_starts_with($roleName, 'sales') => 'SALES',
            str_starts_with($roleName, 'finance') => 'FIN',
            str_starts_with($roleName, 'hr') => 'HR',
            str_starts_with($roleName, 'factory') => 'PROD',
            default => null,
        };
    }
}
