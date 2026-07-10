<?php

declare(strict_types=1);

use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Models\Business;
use App\Models\CompanyInformation;
use App\Models\Employee;
use App\Models\EmployeeIdCard;
use App\Models\User;
use App\Services\Hr\EmployeeIdCardService;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
use Database\Seeders\PermissionsTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

function hrIdCardPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=') ?: '';
}

it('issues an employee ID card number and token', function (): void {
    $employee = Employee::factory()->create([
        'employee_number' => 'EMP-1001',
    ]);

    $issuedCard = app(EmployeeIdCardService::class)->issueCard($employee);

    expect($issuedCard->card_number)->toStartWith('ID-EMP-1001-')
        ->and($issuedCard->token)->not->toBeNull()
        ->and($issuedCard->status)->toBe(EmployeeIdCardService::ACTIVE_STATUS)
        ->and($issuedCard->issue_date)->not->toBeNull()
        ->and($issuedCard->expiry_date)->not->toBeNull();

    $this->assertDatabaseHas('audit_trails', [
        'event_type' => 'hr_id_card',
        'action' => 'card_generated',
        'auditable_type' => EmployeeIdCard::class,
        'auditable_id' => $issuedCard->id,
    ]);
});

it('builds a signed QR payload without sensitive employee data', function (): void {
    $card = app(EmployeeIdCardService::class)->issueCard(Employee::factory()->create([
        'employee_number' => 'EMP-2002',
        'email' => 'private.employee@example.com',
        'phone' => '+2348012345678',
    ]));

    $payload = app(EmployeeIdCardService::class)->qrPayload($card);
    [$employeeNumber, $cardNumber, $token, $signature] = explode('|', $payload);
    $payloadWithoutSignature = implode('|', [$employeeNumber, $cardNumber, $token]);

    expect($employeeNumber)->toBe('EMP-2002')
        ->and($cardNumber)->toBe($card->card_number)
        ->and($token)->toBe($card->token)
        ->and($signature)->toBe(hash_hmac('sha256', $payloadWithoutSignature, (string) config('app.key')))
        ->and($payload)->not->toContain('private.employee@example.com')
        ->and($payload)->not->toContain('+2348012345678');
});

it('shows only safe data on the verification endpoint', function (): void {
    $card = app(EmployeeIdCardService::class)->issueCard(Employee::factory()->create([
        'employee_number' => 'EMP-3003',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada.private@example.com',
        'phone' => '+2348099999999',
        'job_title' => 'HR Analyst',
        'department_code' => 'HR',
    ]));

    $this->get(route('employee-card.verify', $card->token))
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
        'auditable_type' => EmployeeIdCard::class,
        'auditable_id' => $card->id,
    ]);
});

it('does not verify expired or revoked cards as active', function (): void {
    $expiredCard = app(EmployeeIdCardService::class)->issueCard(
        Employee::factory()->create(['first_name' => 'Expired', 'last_name' => 'Employee']),
        now()->subYears(3),
        now()->subDay()
    );

    $this->get(route('employee-card.verify', $expiredCard->token))
        ->assertNotFound()
        ->assertSee('Not active')
        ->assertDontSee('Expired Employee');

    $revokedCard = app(EmployeeIdCardService::class)->issueCard(
        Employee::factory()->create(['first_name' => 'Revoked', 'last_name' => 'Employee'])
    );
    $revokedCard->update(['id_card_status' => 'revoked']);

    $this->get(route('employee-card.verify', $revokedCard->token))
        ->assertNotFound()
        ->assertSee('Not active')
        ->assertDontSee('Revoked Employee');
});

it('downloads a PDF for authorized users', function (): void {
    $user = hrIdCardUserWithPermissions(['hr.employee_id_card.download']);
    $card = app(EmployeeIdCardService::class)->issueCard(Employee::factory()->create([
        'employee_number' => 'EMP-4004',
    ]));

    $this->actingAs($user)
        ->get(route('employees.id-card.download', $card->employee))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');

    $this->assertDatabaseHas('audit_trails', [
        'event_type' => 'hr_id_card',
        'action' => 'card_downloaded',
        'auditable_type' => EmployeeIdCard::class,
        'auditable_id' => $card->id,
    ]);
});

it('embeds employee photo and company logo as data URIs for PDF rendering', function (): void {
    Storage::fake('public');

    Storage::disk('public')->put('employee-photos/employee.png', hrIdCardPng());
    Storage::disk('public')->put('company/logos/logo.png', hrIdCardPng());

    app(EmployeeIdCardService::class)
        ->companyInformation()
        ->forceFill(['company_name' => 'BIFLI Pilot Company', 'logo_path' => 'company/logos/logo.png'])
        ->save();

    $cardRecord = app(EmployeeIdCardService::class)->issueCard(Employee::factory()->create([
        'photo_path' => 'employee-photos/employee.png',
    ]));

    $card = app(EmployeeIdCardService::class)->cardViewData($cardRecord, forPdf: true);

    expect($card['photoSrc'])->toStartWith('data:image/png;base64,')
        ->and($card['logoSrc'])->toStartWith('data:image/png;base64,');
});

it('uses a PNG data URI QR source for PDF rendering while preserving the signed payload', function (): void {
    $employeeCard = app(EmployeeIdCardService::class)->issueCard(Employee::factory()->create([
        'employee_number' => 'EMP-4104',
    ]));

    $service = app(EmployeeIdCardService::class);
    $card = $service->cardViewData($employeeCard, forPdf: true);
    $payload = $service->qrPayload($employeeCard);

    [$employeeNumber, $cardNumber, $token, $signature] = explode('|', $payload);
    $payloadWithoutSignature = implode('|', [$employeeNumber, $cardNumber, $token]);

    expect($card['qrPdfSrc'])->toStartWith('data:image/png;base64,')
        ->and($card['qrSvg'])->toContain('<svg')
        ->and($payload)->not->toContain($employeeCard->employee?->email ?? 'not-present')
        ->and($signature)->toBe(hash_hmac('sha256', $payloadWithoutSignature, (string) config('app.key')));

    $html = view('hr.employee-id-card', [
        'cards' => [$card],
        'print' => false,
    ])->render();

    expect($html)->toContain('data:image/png;base64,')
        ->and($html)->toContain('<img src="data:image/png;base64,')
        ->and($html)->not->toContain('<svg');
});

it('keeps preview and print rendering QR as inline SVG', function (): void {
    $user = hrIdCardUserWithPermissions(['hr.employee_id_card.view']);
    $card = app(EmployeeIdCardService::class)->issueCard(Employee::factory()->create([
        'employee_number' => 'EMP-4204',
    ]));

    $this->actingAs($user)
        ->get(route('employees.id-card.preview', $card->employee))
        ->assertSuccessful()
        ->assertSee('<svg', false)
        ->assertDontSee('data:image/png;base64,', false);

    $this->actingAs($user)
        ->get(route('employees.id-card.print', $card->employee))
        ->assertSuccessful()
        ->assertSee('<svg', false)
        ->assertDontSee('data:image/png;base64,', false);
});

it('falls back cleanly when PDF QR generation is unavailable', function (): void {
    $employeeCard = app(EmployeeIdCardService::class)->issueCard(Employee::factory()->create([
        'first_name' => 'Qr',
        'last_name' => 'Fallback',
    ]));

    expect(app(EmployeeIdCardService::class)->renderQrPngDataUri(''))->toBeNull();

    $card = app(EmployeeIdCardService::class)->cardViewData($employeeCard, forPdf: true);
    $card['qrPdfSrc'] = null;

    $html = view('hr.employee-id-card', [
        'cards' => [$card],
        'print' => false,
    ])->render();

    expect($html)->toContain('QR unavailable')
        ->and($html)->not->toContain('<svg');
});

it('PDF download builds the card with a PNG QR image source', function (): void {
    $user = hrIdCardUserWithPermissions(['hr.employee_id_card.download']);
    $cardRecord = app(EmployeeIdCardService::class)->issueCard(Employee::factory()->create([
        'employee_number' => 'EMP-4304',
    ]));

    $card = app(EmployeeIdCardService::class)->cardViewData($cardRecord, forPdf: true);

    expect($card['qrPdfSrc'])->toStartWith('data:image/png;base64,');

    $this->actingAs($user)
        ->get(route('employees.id-card.download', $cardRecord->employee))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');
});

it('keeps preview and print on normal storage URLs for employee photos', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('employee-photos/preview.png', hrIdCardPng());

    $user = hrIdCardUserWithPermissions(['hr.employee_id_card.view']);
    $card = app(EmployeeIdCardService::class)->issueCard(Employee::factory()->create([
        'photo_path' => 'employee-photos/preview.png',
    ]));

    $this->actingAs($user)
        ->get(route('employees.id-card.preview', $card->employee))
        ->assertSuccessful()
        ->assertSee('/storage/employee-photos/preview.png', false);

    $this->actingAs($user)
        ->get(route('employees.id-card.print', $card->employee))
        ->assertSuccessful()
        ->assertSee('/storage/employee-photos/preview.png', false);
});

it('falls back cleanly when employee photo is missing or unreadable for PDF', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('employee-photos/not-an-image.txt', 'not an image');

    $missingPhotoCard = app(EmployeeIdCardService::class)->issueCard(Employee::factory()->create([
        'first_name' => 'Missing',
        'last_name' => 'Photo',
        'photo_path' => 'employee-photos/missing.png',
    ]));

    $invalidPhotoCard = app(EmployeeIdCardService::class)->issueCard(Employee::factory()->create([
        'first_name' => 'Invalid',
        'last_name' => 'Photo',
        'photo_path' => 'employee-photos/not-an-image.txt',
    ]));

    $missingCard = app(EmployeeIdCardService::class)->cardViewData($missingPhotoCard, forPdf: true);
    $invalidCard = app(EmployeeIdCardService::class)->cardViewData($invalidPhotoCard, forPdf: true);

    expect($missingCard['photoSrc'])->toBeNull()
        ->and($invalidCard['photoSrc'])->toBeNull();

    $html = view('hr.employee-id-card', [
        'cards' => [$missingCard, $invalidCard],
        'print' => false,
    ])->render();

    expect($html)->toContain('MP')
        ->and($html)->toContain('IP');
});

it('renders preview and print from the same PDF-safe card layout', function (): void {
    $user = hrIdCardUserWithPermissions(['hr.employee_id_card.view']);
    $card = app(EmployeeIdCardService::class)->issueCard(Employee::factory()->create([
        'employee_number' => 'EMP-4504',
        'first_name' => 'Layout',
        'last_name' => 'Match',
    ]));

    $preview = $this->actingAs($user)
        ->get(route('employees.id-card.preview', $card->employee))
        ->assertSuccessful()
        ->assertSee('card-header')
        ->assertSee('identity-table')
        ->assertSee('details-table')
        ->assertSee('footer-table')
        ->assertDontSee('display: flex', false)
        ->assertDontSee('display: grid', false)
        ->getContent();

    $print = $this->actingAs($user)
        ->get(route('employees.id-card.print', $card->employee))
        ->assertSuccessful()
        ->assertSee('card-header')
        ->assertSee('identity-table')
        ->assertSee('details-table')
        ->assertSee('footer-table')
        ->assertDontSee('display: flex', false)
        ->assertDontSee('display: grid', false)
        ->getContent();

    expect($preview)->toContain('Layout Match')
        ->and($print)->toContain('Layout Match');
});

it('requires password confirmation before regenerating an ID card', function (): void {
    $user = hrIdCardUserWithPermissions([
        'hr.employee.view_any',
        'hr.employee_id_card.regenerate',
    ]);

    $employee = Employee::factory()->create();
    $card = app(EmployeeIdCardService::class)->issueCard($employee);
    $originalToken = $card->token;

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
        'auditable_type' => EmployeeIdCard::class,
        'auditable_id' => $employee->fresh()->activeIdCard->id,
    ]);
});

it('blocks unauthorized users from generating and downloading ID cards', function (): void {
    $user = hrIdCardUserWithPermissions(['hr.employee.view_any']);
    $employee = Employee::factory()->create();
    app(EmployeeIdCardService::class)->issueCard($employee);

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
