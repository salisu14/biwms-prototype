<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\Employee;
use App\Models\User;
use App\Services\AuditTrailService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Throwable;

class EmployeeOnboardingService
{
    public function __construct(
        protected AuditTrailService $auditTrailService
    ) {}

    /**
     * Create an employee and optionally create a linked user account.
     *
     * @throws Throwable
     */
    public function create(array $data): Employee
    {
        $userAccountData = $this->extractUserAccountData($data);

        return DB::transaction(function () use ($data, $userAccountData): Employee {
            $employee = Employee::create($data);

            $this->logAudit(
                action: 'employee_created',
                model: $employee,
                description: "Employee {$employee->employee_number} created.",
            );

            if ((bool) ($userAccountData['create_user_account'] ?? false)) {
                $this->createUserAccountForEmployee($employee, $userAccountData);
            }

            return $employee->fresh(['user']);
        });
    }

    /**
     * Create a linked user account for an existing employee.
     *
     * @throws Throwable
     */
    public function createUserAccountForEmployee(Employee $employee, array $userData): User
    {
        return DB::transaction(function () use ($employee, $userData): User {
            $employee = $employee->fresh('user');

            if (! $employee) {
                throw ValidationException::withMessages([
                    'employee' => 'Employee record could not be found.',
                ]);
            }

            if ($employee->hasUserAccount()) {
                throw ValidationException::withMessages([
                    'employee_id' => "Employee {$employee->employee_number} already has a linked user account.",
                ]);
            }

            $loginEmail = trim((string) ($userData['login_email'] ?? ''));

            if ($loginEmail === '') {
                throw ValidationException::withMessages([
                    'login_email' => 'Login email is required when creating a user account.',
                ]);
            }

            if (User::query()->where('email', $loginEmail)->exists()) {
                throw ValidationException::withMessages([
                    'login_email' => 'This login email is already in use.',
                ]);
            }

            $passwordMethod = (string) ($userData['password_method'] ?? 'send_password_reset');
            $temporaryPassword = (string) ($userData['temporary_password'] ?? '');

            $password = match ($passwordMethod) {
                'temporary_password' => $this->makeTemporaryPasswordHash($temporaryPassword),
                'send_password_reset' => Hash::make(str()->random(64)),
                default => throw ValidationException::withMessages([
                    'password_method' => 'Invalid password setup method.',
                ]),
            };

            $user = User::create([
                'employee_id' => $employee->id,
                'name' => trim("{$employee->first_name} {$employee->last_name}"),
                'email' => $loginEmail,
                'password' => $password,
            ]);

            $this->logAudit(
                action: 'user_account_created',
                model: $user,
                description: "User account created for employee {$employee->employee_number}.",
                metadata: [
                    'employee_id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'password_method' => $passwordMethod,
                ],
            );

            $initialRole = trim((string) ($userData['initial_role'] ?? ''));

            if ($initialRole !== '') {
                $this->assignInitialRole($user, $initialRole);
            }

            if ($passwordMethod === 'send_password_reset') {
                Password::sendResetLink(['email' => $user->email]);
            }

            return $user->fresh(['employee', 'roles']);
        });
    }

    /**
     * Remove onboarding-only fields from employee payload and return them separately.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractUserAccountData(array &$data): array
    {
        $userAccountData = [
            'create_user_account' => (bool) ($data['create_user_account'] ?? false),
            'login_email' => $data['login_email'] ?? null,
            'initial_role' => $data['initial_role'] ?? null,
            'password_method' => $data['password_method'] ?? 'send_password_reset',
            'temporary_password' => $data['temporary_password'] ?? null,
        ];

        unset(
            $data['create_user_account'],
            $data['login_email'],
            $data['initial_role'],
            $data['password_method'],
            $data['temporary_password'],
        );

        return $userAccountData;
    }

    protected function makeTemporaryPasswordHash(string $temporaryPassword): string
    {
        if (strlen($temporaryPassword) < 8) {
            throw ValidationException::withMessages([
                'temporary_password' => 'Temporary password must be at least 8 characters.',
            ]);
        }

        return Hash::make($temporaryPassword);
    }

    protected function assignInitialRole(User $user, string $roleName): void
    {
        $role = Role::query()
            ->where('guard_name', 'web')
            ->where('name', $roleName)
            ->first();

        if (! $role) {
            throw ValidationException::withMessages([
                'initial_role' => "The selected role '{$roleName}' does not exist for the web guard.",
            ]);
        }

        $user->assignRole($role);

        $this->auditTrailService->recordPermissionChange(
            auditable: $user,
            action: 'role_assigned',
            metadata: [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'user_id' => $user->id,
                'user_email' => $user->email,
            ],
        );
    }

    protected function logAudit(
        string $action,
        Model $model,
        string $description,
        ?array $metadata = null,
    ): void {
        $this->auditTrailService->recordGeneric(
            eventType: 'onboarding',
            action: $action,
            auditable: $model,
            userId: auth()->id(),
            description: $description,
            metadata: $metadata,
        );
    }
}
