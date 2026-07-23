<?php

declare(strict_types=1);

use App\Enums\ReferrerType;
use App\Filament\Resources\Referrers\Pages\CreateReferrer;
use App\Models\AuditTrail;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\Permission;
use App\Models\Referrer;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Policies\ReferrerPolicy;
use App\Services\Sales\ReferrerNumberSeriesSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    referrerManagementRole('sales-manager', [
        'sales.referrer.view_any',
        'sales.referrer.view',
        'sales.referrer.create',
        'sales.referrer.update',
        'sales.referrer.delete',
        'sales.referrer.delete_any',
        'sales.referrer.restore',
        'sales.referrer.restore_any',
    ]);

    referrerManagementRole('sales-representative', [
        'sales.referrer.view_any',
        'sales.referrer.view',
    ]);
});

function referrerManagementRole(string $roleName, array $permissions): Role
{
    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $role = Role::query()->firstOrCreate([
        'name' => $roleName,
        'guard_name' => 'web',
    ]);
    $role->syncPermissions($permissions);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $role;
}

function referrerManagementUser(string $roleName): User
{
    $user = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole(Role::query()->where('name', $roleName)->firstOrFail());

    return $user;
}

function referrerNumberSeriesLine(): ?NumberSeriesLine
{
    return NumberSeriesLine::query()
        ->whereHas('series', fn ($query) => $query->where('code', ReferrerNumberSeriesSetupService::CODE))
        ->first();
}

it('creates an independent referrer and generates a code from the number series', function (): void {
    app(ReferrerNumberSeriesSetupService::class)->ensure();

    $referrer = Referrer::query()->create([
        'name' => 'Aisha Bello',
        'type' => ReferrerType::INDIVIDUAL,
        'commission_eligible' => false,
        'is_active' => false,
    ]);

    expect($referrer->code)->toBe('REF-000001')
        ->and($referrer->commission_eligible)->toBeFalse()
        ->and($referrer->is_active)->toBeFalse()
        ->and(referrerNumberSeriesLine()?->last_no_used)->toBe(1);
});

it('enforces type dependent linked records', function (): void {
    app(ReferrerNumberSeriesSetupService::class)->ensure();

    expect(fn () => Referrer::query()->create([
        'name' => 'Missing Contact',
        'type' => ReferrerType::CONTACT,
    ]))->toThrow(ValidationException::class);

    expect(fn () => Referrer::query()->create([
        'name' => 'Missing Customer',
        'type' => ReferrerType::EXISTING_CUSTOMER,
    ]))->toThrow(ValidationException::class);

    expect(fn () => Referrer::query()->create([
        'name' => 'Missing Employee',
        'type' => ReferrerType::EMPLOYEE,
    ]))->toThrow(ValidationException::class);

    expect(fn () => Referrer::query()->create([
        'name' => 'Missing Vendor',
        'type' => ReferrerType::VENDOR,
    ]))->toThrow(ValidationException::class);

    $contact = Contact::factory()->create();
    $customer = Customer::factory()->create();
    $employee = Employee::factory()->create();
    $vendor = Vendor::factory()->create();

    expect(Referrer::query()->create([
        'name' => 'Contact Referrer',
        'type' => ReferrerType::CONTACT,
        'contact_id' => $contact->getKey(),
    ])->contact_id)->toBe($contact->getKey())
        ->and(Referrer::query()->create([
            'name' => 'Customer Referrer',
            'type' => ReferrerType::EXISTING_CUSTOMER,
            'customer_id' => $customer->getKey(),
        ])->customer_id)->toBe($customer->getKey())
        ->and(Referrer::query()->create([
            'name' => 'Employee Referrer',
            'type' => ReferrerType::EMPLOYEE,
            'employee_id' => $employee->getKey(),
        ])->employee_id)->toBe($employee->getKey())
        ->and(Referrer::query()->create([
            'name' => 'Vendor Referrer',
            'type' => ReferrerType::VENDOR,
            'vendor_id' => $vendor->getKey(),
        ])->vendor_id)->toBe($vendor->getKey());
});

it('preserves existing number series counters and does not consume on failed creation', function (): void {
    $result = app(ReferrerNumberSeriesSetupService::class)->ensure();
    NumberSeriesLine::query()->findOrFail($result['line_id'])->update(['last_no_used' => 41]);

    app(ReferrerNumberSeriesSetupService::class)->ensure();

    expect(referrerNumberSeriesLine()?->last_no_used)->toBe(41);

    expect(fn () => Referrer::query()->create([
        'name' => 'Missing Contact',
        'type' => ReferrerType::CONTACT,
    ]))->toThrow(ValidationException::class);

    expect(referrerNumberSeriesLine()?->last_no_used)->toBe(41);
});

it('blocks referrer creation with a clear notification when setup is missing', function (): void {
    $user = referrerManagementUser('sales-manager');

    Livewire::actingAs($user)
        ->test(CreateReferrer::class)
        ->fillForm([
            'name' => 'Setup Missing Referrer',
            'type' => ReferrerType::INDIVIDUAL->value,
            'commission_eligible' => true,
            'is_active' => true,
        ])
        ->call('create')
        ->assertNotified('Referrer Number Series is not configured');

    expect(Referrer::query()->count())->toBe(0)
        ->and(NumberSeries::query()->where('code', ReferrerNumberSeriesSetupService::CODE)->exists())->toBeFalse();
});

it('renders referrer pages for authorized users', function (): void {
    app(ReferrerNumberSeriesSetupService::class)->ensure();
    $user = referrerManagementUser('sales-manager');
    $referrer = Referrer::query()->create([
        'name' => 'Rendered Referrer',
        'type' => ReferrerType::INDIVIDUAL,
    ]);

    foreach ([
        '/admin/referrers',
        '/admin/referrers/create',
        "/admin/referrers/{$referrer->getKey()}",
        "/admin/referrers/{$referrer->getKey()}/edit",
    ] as $url) {
        $this->actingAs($user)
            ->withSession(['two_factor_passed_at' => now()->timestamp])
            ->get($url)
            ->assertSuccessful();
    }
});

it('enforces referrer authorization and policy registration', function (): void {
    $manager = referrerManagementUser('sales-manager');
    $salesperson = referrerManagementUser('sales-representative');
    $unauthorized = User::factory()->create();
    $referrer = Referrer::factory()->create();

    expect(Gate::getPolicyFor(Referrer::class))->toBeInstanceOf(ReferrerPolicy::class)
        ->and($manager->can('create', Referrer::class))->toBeTrue()
        ->and($manager->can('update', $referrer))->toBeTrue()
        ->and($salesperson->can('viewAny', Referrer::class))->toBeTrue()
        ->and($salesperson->can('create', Referrer::class))->toBeFalse()
        ->and($unauthorized->can('create', Referrer::class))->toBeFalse();
});

it('records audit trail for referrer lifecycle changes', function (): void {
    app(ReferrerNumberSeriesSetupService::class)->ensure();

    $referrer = Referrer::query()->create([
        'name' => 'Audited Referrer',
        'type' => ReferrerType::INDIVIDUAL,
    ]);

    $referrer->update([
        'is_active' => false,
        'commission_eligible' => false,
    ]);
    $referrer->delete();
    $referrer->restore();

    expect(AuditTrail::query()->where('auditable_type', $referrer->getMorphClass())->where('auditable_id', $referrer->id)->where('action', 'created')->exists())->toBeTrue()
        ->and(AuditTrail::query()->where('auditable_type', $referrer->getMorphClass())->where('auditable_id', $referrer->id)->where('action', 'deactivated')->exists())->toBeTrue()
        ->and(AuditTrail::query()->where('auditable_type', $referrer->getMorphClass())->where('auditable_id', $referrer->id)->where('action', 'commission_disabled')->exists())->toBeTrue()
        ->and(AuditTrail::query()->where('auditable_type', $referrer->getMorphClass())->where('auditable_id', $referrer->id)->where('action', 'deleted')->exists())->toBeTrue()
        ->and(AuditTrail::query()->where('auditable_type', $referrer->getMorphClass())->where('auditable_id', $referrer->id)->where('action', 'restored')->exists())->toBeTrue();
});
