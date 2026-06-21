<?php

declare(strict_types=1);

namespace Tests\Feature\Services\HR;

use App\Models\AuditTrail;
use App\Models\Employee;
use App\Models\User;
use App\Services\HR\EmployeeOnboardingService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeOnboardingTest extends TestCase
{
    protected EmployeeOnboardingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(EmployeeOnboardingService::class);

        Role::firstOrCreate([
            'name' => 'hr-officer',
            'guard_name' => 'web',
        ]);

        Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);
    }

    public function test_it_creates_employee_only_successfully(): void
    {
        $data = Employee::factory()->make()->toArray();

        $data['create_user_account'] = false;

        $employee = $this->service->create($data);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'employee_number' => $data['employee_number'],
            'email' => $data['email'],
        ]);

        $this->assertFalse($employee->hasUserAccount());

        $this->assertDatabaseMissing('users', [
            'employee_id' => $employee->id,
        ]);
    }

    public function test_it_creates_employee_and_user_with_temporary_password(): void
    {
        $data = Employee::factory()->make()->toArray();

        $data['create_user_account'] = true;
        $data['login_email'] = 'login@example.com';
        $data['initial_role'] = 'hr-officer';
        $data['password_method'] = 'temporary_password';
        $data['temporary_password'] = 'SecretPassword123!';

        $employee = $this->service->create($data)->fresh('user');

        $this->assertTrue($employee->hasUserAccount());

        $user = $employee->user;

        $this->assertInstanceOf(User::class, $user);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'login@example.com',
            'employee_id' => $employee->id,
        ]);

        $this->assertSame(
            trim("{$employee->first_name} {$employee->last_name}"),
            $user->name
        );

        $this->assertTrue(Hash::check('SecretPassword123!', $user->password));
        $this->assertTrue($user->hasRole('hr-officer'));
    }

    public function test_it_creates_user_with_password_reset_flow(): void
    {
        Notification::fake();

        $data = Employee::factory()->make()->toArray();

        $data['create_user_account'] = true;
        $data['login_email'] = 'reset@example.com';
        $data['initial_role'] = 'admin';
        $data['password_method'] = 'send_password_reset';

        $employee = $this->service->create($data)->fresh('user');

        $user = $employee->user;

        $this->assertInstanceOf(User::class, $user);

        $this->assertNotNull($user->password);
        $this->assertTrue($user->hasRole('admin'));

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_it_can_create_user_account_for_existing_employee(): void
    {
        Notification::fake();

        $employee = Employee::factory()->create();

        $user = $this->service->createUserAccountForEmployee($employee, [
            'login_email' => 'existing.employee@example.com',
            'initial_role' => 'hr-officer',
            'password_method' => 'send_password_reset',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'employee_id' => $employee->id,
            'email' => 'existing.employee@example.com',
        ]);

        $this->assertTrue($employee->fresh()->hasUserAccount());
        $this->assertTrue($user->hasRole('hr-officer'));

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_it_blocks_duplicate_user_creation_for_same_employee(): void
    {
        $employee = Employee::factory()->create();

        User::factory()->create([
            'employee_id' => $employee->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->createUserAccountForEmployee($employee, [
            'login_email' => 'new-email@example.com',
            'initial_role' => 'hr-officer',
            'password_method' => 'send_password_reset',
        ]);
    }

    public function test_it_blocks_duplicate_login_email(): void
    {
        User::factory()->create([
            'email' => 'taken@example.com',
        ]);

        $employee = Employee::factory()->create();

        $this->expectException(ValidationException::class);

        $this->service->createUserAccountForEmployee($employee, [
            'login_email' => 'taken@example.com',
            'initial_role' => 'hr-officer',
            'password_method' => 'send_password_reset',
        ]);
    }

    public function test_it_logs_audit_trail_for_employee_user_and_role_assignment(): void
    {
        Notification::fake();

        $data = Employee::factory()->make()->toArray();

        $data['create_user_account'] = true;
        $data['login_email'] = 'audit@example.com';
        $data['initial_role'] = 'hr-officer';
        $data['password_method'] = 'send_password_reset';

        $employee = $this->service->create($data)->fresh('user');

        $user = $employee->user;

        $this->assertDatabaseHas('audit_trails', [
            'event_type' => 'onboarding',
            'action' => 'employee_created',
            'auditable_type' => Employee::class,
            'auditable_id' => $employee->id,
        ]);

        $this->assertDatabaseHas('audit_trails', [
            'event_type' => 'onboarding',
            'action' => 'user_account_created',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);

        $this->assertDatabaseHas('audit_trails', [
            'event_type' => 'permission',
            'action' => 'role_assigned',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);

        $this->assertGreaterThanOrEqual(3, AuditTrail::query()->count());
    }

    public function test_inactive_employee_access_check(): void
    {
        $employee = Employee::factory()->create([
            'is_active' => false,
        ]);

        $user = User::factory()->create([
            'employee_id' => $employee->id,
        ]);

        $this->assertFalse($user->isEmployeeActive());

        $employee->update([
            'is_active' => true,
        ]);

        $this->assertTrue($user->fresh('employee')->isEmployeeActive());
    }
}
