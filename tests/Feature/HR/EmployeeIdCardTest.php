<?php

declare(strict_types=1);

use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Models\Business;
use App\Models\CompanyInformation;
use App\Models\Employee;
use App\Models\User;
use App\Services\Hr\EmployeeIdCardService;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
use Database\Seeders\PermissionsTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(PermissionsTableSeeder::class);

    CompanyInformation::getInstance()
        ->forceFill(['company_name' => 'BIFLI Pilot Company'])
        ->save();

    CompanyInformation::query()->update(['company_name' => 'BIFLI Pilot Company']);

    $business = Business::query()->firstOrCreate(
        ['code' => 'PILOT'],
        ['name' => 'Pilot Business', 'is_active' => true]
    );

    CompanyInformation::getInstance($business->id)
        ->forceFill(['company_name' => 'BIFLI Pilot Company'])
        ->save();
});

function hrIdCardUserWithPermissions(array $permissions): User
{
    $role = Role::query()->create([
        'name' => 'id-card-test-role-'.str()->random(8),
        'guard_name' => 'web',
    ]);

    $role->givePermissionTo($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('issues an employee ID card number and token', function (): void {
    $employee = Employee::factory()->create([
        'employee_number' => 'EMP-1001',
    ]);

    $issuedEmployee = app(EmployeeIdCardService::class)->issueCard($employee);

    expect($issuedEmployee->id_card_number)->toStartWith('ID-EMP-1001-')
        ->and($issuedEmployee->id_card_token)->not->toBeNull()
        ->and($issuedEmployee->id_card_status)->toBe(EmployeeIdCardService::ACTIVE_STATUS)
        ->and($issuedEmployee->id_card_issue_date)->not->toBeNull()
        ->and($issuedEmployee->id_card_expiry_date)->not->toBeNull();

    $this->assertDatabaseHas('audit_trails', [
        'event_type' => 'hr_id_card',
        'action' => 'card_generated',
        'auditable_type' => Employee::class,
        'auditable_id' => $issuedEmployee->id,
    ]);
});

it('builds a signed QR payload without sensitive employee data', function (): void {
    $employee = app(EmployeeIdCardService::class)->issueCard(Employee::factory()->create([
        'employee_number' => 'EMP-2002',
        'email' => 'private.employee@example.com',
        'phone' => '+2348012345678',
    ]));

    $payload = app(EmployeeIdCardService::class)->qrPayload($employee);
    [$employeeNumber, $cardNumber, $token, $signature] = explode('|', $payload);
    $payloadWithoutSignature = implode('|', [$employeeNumber, $cardNumber, $token]);

    expect($employeeNumber)->toBe('EMP-2002')
        ->and($cardNumber)->toBe($employee->id_card_number)
        ->and($token)->toBe($employee->id_card_token)
        ->and($signature)->toBe(hash_hmac('sha256', $payloadWithoutSignature, (string) config('app.key')))
        ->and($payload)->not->toContain('private.employee@example.com')
        ->and($payload)->not->toContain('+2348012345678');
});

it('shows only safe data on the verification endpoint', function (): void {
    $employee = app(EmployeeIdCardService::class)->issueCard(Employee::factory()->create([
        'employee_number' => 'EMP-3003',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada.private@example.com',
        'phone' => '+2348099999999',
        'job_title' => 'HR Analyst',
        'department_code' => 'HR',
    ]));

    $this->get(route('employee-card.verify', $employee->id_card_token))
        ->assertSuccessful()
        ->assertSee('Active card')
        ->assertSee('Ada Lovelace')
        ->assertSee('EMP-3003')
        ->assertSee('HR Analyst')
        ->assertSee('Employee Card Verification')
        ->assertDontSee('ada.private@example.com')
        ->assertDontSee('+2348099999999')
        ->assertDontSee('salary')
        ->assertDontSee('bank');

    $this->assertDatabaseHas('audit_trails', [
        'event_type' => 'hr_id_card',
        'action' => 'card_verified',
        'auditable_type' => Employee::class,
        'auditable_id' => $employee->id,
    ]);
});

it('does not verify expired or revoked cards as active', function (): void {
    $expiredEmployee = app(EmployeeIdCardService::class)->issueCard(
        Employee::factory()->create(['first_name' => 'Expired', 'last_name' => 'Employee']),
        now()->subYears(3),
        now()->subDay()
    );

    $this->get(route('employee-card.verify', $expiredEmployee->id_card_token))
        ->assertNotFound()
        ->assertSee('Not active')
        ->assertDontSee('Expired Employee');

    $revokedEmployee = app(EmployeeIdCardService::class)->issueCard(
        Employee::factory()->create(['first_name' => 'Revoked', 'last_name' => 'Employee'])
    );
    $revokedEmployee->update(['id_card_status' => 'revoked']);

    $this->get(route('employee-card.verify', $revokedEmployee->id_card_token))
        ->assertNotFound()
        ->assertSee('Not active')
        ->assertDontSee('Revoked Employee');
});

it('downloads a PDF for authorized users', function (): void {
    $user = hrIdCardUserWithPermissions(['hr.employee_id_card.download']);
    $employee = app(EmployeeIdCardService::class)->issueCard(Employee::factory()->create([
        'employee_number' => 'EMP-4004',
    ]));

    $this->actingAs($user)
        ->get(route('employees.id-card.download', $employee))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');

    $this->assertDatabaseHas('audit_trails', [
        'event_type' => 'hr_id_card',
        'action' => 'card_downloaded',
        'auditable_type' => Employee::class,
        'auditable_id' => $employee->id,
    ]);
});

it('requires password confirmation before regenerating an ID card', function (): void {
    $user = hrIdCardUserWithPermissions([
        'hr.employee.view_any',
        'hr.employee_id_card.regenerate',
    ]);

    $employee = app(EmployeeIdCardService::class)->issueCard(Employee::factory()->create());
    $originalToken = $employee->id_card_token;

    Livewire::actingAs($user)
        ->test(ListEmployees::class)
        ->callTableAction('regenerateIdCard', $employee)
        ->assertHasTableActionErrors([SensitiveActionPasswordConfirmation::FIELD]);

    expect($employee->fresh()->id_card_token)->toBe($originalToken);

    Livewire::actingAs($user)
        ->test(ListEmployees::class)
        ->callTableAction('regenerateIdCard', $employee, data: [
            SensitiveActionPasswordConfirmation::FIELD => 'password',
        ])
        ->assertHasNoTableActionErrors();

    expect($employee->fresh()->id_card_token)->not->toBe($originalToken);

    $this->assertDatabaseHas('audit_trails', [
        'event_type' => 'hr_id_card',
        'action' => 'card_regenerated',
        'auditable_type' => Employee::class,
        'auditable_id' => $employee->id,
    ]);
});

it('blocks unauthorized users from generating and downloading ID cards', function (): void {
    $user = hrIdCardUserWithPermissions(['hr.employee.view_any']);
    $employee = app(EmployeeIdCardService::class)->issueCard(Employee::factory()->create());

    Livewire::actingAs($user)
        ->test(ListEmployees::class)
        ->assertTableActionHidden('generateIdCard', $employee)
        ->assertTableActionHidden('downloadIdCard', $employee)
        ->assertTableActionHidden('regenerateIdCard', $employee);

    $response = $this->actingAs($user)
        ->get(route('employees.id-card.download', $employee));

    expect($response->getStatusCode())->toBeIn([403, 404]);
});

it('allows an authorized user to generate a card from the employee table action', function (): void {
    $user = hrIdCardUserWithPermissions([
        'hr.employee.view_any',
        'hr.employee_id_card.generate',
    ]);
    $employee = Employee::factory()->create();

    Livewire::actingAs($user)
        ->test(ListEmployees::class)
        ->callTableAction('generateIdCard', $employee)
        ->assertHasNoTableActionErrors();

    expect($employee->fresh()->id_card_token)->not->toBeNull()
        ->and($employee->fresh()->id_card_number)->not->toBeNull();
});
