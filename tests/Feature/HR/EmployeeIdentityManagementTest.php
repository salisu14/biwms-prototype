<?php

declare(strict_types=1);

use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Models\Employee;
use App\Models\EmployeeIdCard;
use App\Models\EmployeeIdCardHistory;
use App\Models\EmployeeIdCardPrintBatch;
use App\Models\EmployeeIdCardTemplate;
use App\Models\EmployeeIdCardVerificationLog;
use App\Models\User;
use App\Services\Hr\EmployeeIdCardService;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
use Database\Seeders\PermissionsTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(PermissionsTableSeeder::class);
});

function employeeIdentityUserWithPermissions(array $permissions): User
{
    $role = Role::query()->create([
        'name' => 'employee-identity-role-'.str()->random(8),
        'guard_name' => 'web',
    ]);
    $role->givePermissionTo($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('issues a standalone card record and mirrors legacy employee fields', function (): void {
    $employee = Employee::factory()->create(['employee_number' => 'EMP-ID-01']);

    $card = app(EmployeeIdCardService::class)->issueCard($employee);

    expect($card)->toBeInstanceOf(EmployeeIdCard::class)
        ->and($card->employee_id)->toBe($employee->id)
        ->and($card->card_number)->toStartWith('ID-EMP-ID-01-')
        ->and($card->token)->not->toBeNull()
        ->and($card->status)->toBe(EmployeeIdCard::STATUS_ACTIVE);

    $employee->refresh();

    expect($employee->id_card_number)->toBe($card->card_number)
        ->and($employee->id_card_token)->toBe($card->token)
        ->and($employee->activeIdCard?->id)->toBe($card->id);
});

it('keeps only one active card per employee', function (): void {
    $employee = Employee::factory()->create();

    $first = app(EmployeeIdCardService::class)->issueCard($employee);
    $second = app(EmployeeIdCardService::class)->issueCard($employee);

    expect($second->id)->toBe($first->id)
        ->and(EmployeeIdCard::query()->where('employee_id', $employee->id)->where('status', EmployeeIdCard::STATUS_ACTIVE)->count())->toBe(1);
});

it('replacing a card marks old card replaced and creates a new active card', function (): void {
    $employee = Employee::factory()->create();
    $oldCard = app(EmployeeIdCardService::class)->issueCard($employee);

    $newCard = app(EmployeeIdCardService::class)->replaceCard($oldCard, 'Photo update');

    expect($oldCard->fresh()->status)->toBe(EmployeeIdCard::STATUS_REPLACED)
        ->and($newCard->status)->toBe(EmployeeIdCard::STATUS_ACTIVE)
        ->and($newCard->replaced_card_id)->toBe($oldCard->id)
        ->and($employee->fresh()->activeIdCard?->id)->toBe($newCard->id)
        ->and(EmployeeIdCard::query()->where('employee_id', $employee->id)->where('status', EmployeeIdCard::STATUS_ACTIVE)->count())->toBe(1);
});

it('revoking a card invalidates public verification and records logs', function (): void {
    $employee = Employee::factory()->create(['first_name' => 'Verify', 'last_name' => 'Blocked']);
    $card = app(EmployeeIdCardService::class)->issueCard($employee);

    app(EmployeeIdCardService::class)->revokeCard($card, 'Employment ended');

    $this->get(route('employee-card.verify', $card->token))
        ->assertNotFound()
        ->assertSee('Not active')
        ->assertDontSee('Verify Blocked');

    expect(EmployeeIdCardVerificationLog::query()->where('card_id', $card->id)->where('result', 'invalid')->exists())->toBeTrue()
        ->and(EmployeeIdCardHistory::query()->where('card_id', $card->id)->where('event', 'revoked')->exists())->toBeTrue();
});

it('EmployeeResource shortcut uses the same standalone card service', function (): void {
    $user = employeeIdentityUserWithPermissions([
        'hr.employee.view_any',
        'hr.employee_id_card.generate',
        'hr.employee_id_card.regenerate',
    ]);
    $employee = Employee::factory()->create();

    Livewire::actingAs($user)
        ->test(ListEmployees::class)
        ->callTableAction('generateIdCard', $employee)
        ->assertHasNoTableActionErrors();

    $firstCard = $employee->fresh()->activeIdCard;

    Livewire::actingAs($user)
        ->test(ListEmployees::class)
        ->callTableAction('regenerateIdCard', $employee, data: [
            SensitiveActionPasswordConfirmation::FIELD => 'password',
        ])
        ->assertHasNoTableActionErrors();

    expect($firstCard?->fresh()->status)->toBe(EmployeeIdCard::STATUS_REPLACED)
        ->and($employee->fresh()->activeIdCard?->id)->not->toBe($firstCard?->id);
});

it('bulk print creates a print batch and items', function (): void {
    $cards = Employee::factory()
        ->count(2)
        ->create()
        ->map(fn (Employee $employee) => app(EmployeeIdCardService::class)->issueCard($employee));

    $batch = app(EmployeeIdCardService::class)->createPrintBatch($cards, '4-up');

    expect($batch)->toBeInstanceOf(EmployeeIdCardPrintBatch::class)
        ->and($batch->layout)->toBe('4-up')
        ->and($batch->items()->count())->toBe(2);
});

it('creates history events for issue replace print and verify', function (): void {
    $employee = Employee::factory()->create();
    $card = app(EmployeeIdCardService::class)->issueCard($employee);
    app(EmployeeIdCardService::class)->markPrinted($card);

    $this->get(route('employee-card.verify', $card->token))->assertSuccessful();

    $replacement = app(EmployeeIdCardService::class)->replaceCard($card);

    expect(EmployeeIdCardHistory::query()->where('card_id', $card->id)->where('event', 'issued')->exists())->toBeTrue()
        ->and(EmployeeIdCardHistory::query()->where('card_id', $card->id)->where('event', 'printed')->exists())->toBeTrue()
        ->and(EmployeeIdCardHistory::query()->where('card_id', $card->id)->where('event', 'verified')->exists())->toBeTrue()
        ->and(EmployeeIdCardHistory::query()->where('card_id', $card->id)->where('event', 'replaced')->exists())->toBeTrue()
        ->and(EmployeeIdCardHistory::query()->where('card_id', $replacement->id)->where('event', 'issued')->exists())->toBeTrue();
});

it('has explicit permissions and policy checks for identity records', function (): void {
    $user = employeeIdentityUserWithPermissions([
        'hr.employee_id_card.view_any',
        'hr.employee_id_card.view',
        'hr.employee_id_card_template.view_any',
        'hr.employee_id_card_print_batch.view_any',
        'hr.employee_id_card_history.view_any',
        'hr.employee_id_card_verification_log.view_any',
    ]);

    $card = app(EmployeeIdCardService::class)->issueCard(Employee::factory()->create());

    expect($user->can('viewAny', EmployeeIdCard::class))->toBeTrue()
        ->and($user->can('view', $card))->toBeTrue()
        ->and($user->can('viewAny', EmployeeIdCardTemplate::class))->toBeTrue()
        ->and($user->can('viewAny', EmployeeIdCardPrintBatch::class))->toBeTrue()
        ->and($user->can('viewAny', EmployeeIdCardHistory::class))->toBeTrue()
        ->and($user->can('viewAny', EmployeeIdCardVerificationLog::class))->toBeTrue();
});

it('keeps legacy employee columns while standalone tables exist', function (): void {
    expect(Schema::hasTable('employee_id_cards'))->toBeTrue()
        ->and(Schema::hasTable('employee_id_card_templates'))->toBeTrue()
        ->and(Schema::hasTable('employee_id_card_print_batches'))->toBeTrue()
        ->and(Schema::hasTable('employee_id_card_histories'))->toBeTrue()
        ->and(Schema::hasTable('employee_id_card_verification_logs'))->toBeTrue()
        ->and(Schema::hasColumn('employees', 'id_card_number'))->toBeTrue()
        ->and(Schema::hasColumn('employees', 'id_card_token'))->toBeTrue();
});

it('keeps preview print and PDF on the shared card template', function (): void {
    $card = app(EmployeeIdCardService::class)->issueCard(Employee::factory()->create());

    $browserHtml = view('hr.employee-id-card', [
        'cards' => [app(EmployeeIdCardService::class)->cardViewData($card)],
        'print' => false,
    ])->render();

    $pdfHtml = view('hr.employee-id-card', [
        'cards' => [app(EmployeeIdCardService::class)->cardViewData($card, forPdf: true)],
        'print' => false,
    ])->render();

    expect($browserHtml)->toContain('identity-table')
        ->and($browserHtml)->toContain('<svg')
        ->and($pdfHtml)->toContain('identity-table')
        ->and($pdfHtml)->toContain('data:image/png;base64,');
});
