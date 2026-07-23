<?php

declare(strict_types=1);

use App\Enums\CustomerReferralStatus;
use App\Enums\ReferrerType;
use App\Models\AuditTrail;
use App\Models\Customer;
use App\Models\CustomerReferral;
use App\Models\Referrer;
use App\Models\User;
use App\Services\Sales\CustomerReferralService;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function referralService(): CustomerReferralService
{
    return app(CustomerReferralService::class);
}

it('assigns an optional referrer without storing referrer_id on customers', function (): void {
    $customer = Customer::factory()->create();
    $referrer = Referrer::factory()->create();

    expect($customer->getAttributes())->not->toHaveKey('referrer_id');

    $referral = referralService()->assign($customer, $referrer, [
        'effective_from' => today(),
        'referral_source' => 'Walk-in',
    ], User::factory()->create()->id);

    expect($referral->status)->toBe(CustomerReferralStatus::ACTIVE)
        ->and($referral->customer_id)->toBe($customer->id)
        ->and($referral->referrer_id)->toBe($referrer->id)
        ->and($customer->refresh()->getAttributes())->not->toHaveKey('referrer_id')
        ->and($customer->primaryReferral?->referrer_id)->toBe($referrer->id);
});

it('blocks inactive referrers self referrals and duplicate active primary referrals', function (): void {
    $customer = Customer::factory()->create();
    $activeReferrer = Referrer::factory()->create();
    $inactiveReferrer = Referrer::factory()->create(['is_active' => false]);
    $selfReferrer = Referrer::factory()->create([
        'type' => ReferrerType::EXISTING_CUSTOMER,
        'customer_id' => $customer->id,
    ]);

    expect(fn () => referralService()->assign($customer, $inactiveReferrer, ['effective_from' => today()]))
        ->toThrow(DomainException::class, 'Inactive referrers cannot be assigned');

    expect(fn () => referralService()->assign($customer, $selfReferrer, ['effective_from' => today()]))
        ->toThrow(DomainException::class, 'A customer cannot refer itself');

    referralService()->assign($customer, $activeReferrer, ['effective_from' => today()]);

    expect(fn () => referralService()->assign($customer, Referrer::factory()->create(), ['effective_from' => today()]))
        ->toThrow(DomainException::class, 'Customer already has an active primary referrer');
});

it('enforces one open active primary referral at the database level', function (): void {
    $customer = Customer::factory()->create();

    CustomerReferral::factory()->create([
        'customer_id' => $customer->id,
        'status' => CustomerReferralStatus::ACTIVE,
        'is_primary' => true,
        'effective_to' => null,
    ]);

    expect(fn () => CustomerReferral::factory()->create([
        'customer_id' => $customer->id,
        'status' => CustomerReferralStatus::ACTIVE,
        'is_primary' => true,
        'effective_to' => null,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('changes referrer by ending the old row and creating new history', function (): void {
    $customer = Customer::factory()->create();
    $oldReferrer = Referrer::factory()->create(['name' => 'Old Referrer']);
    $newReferrer = Referrer::factory()->create(['name' => 'New Referrer']);
    $oldReferral = referralService()->assign($customer, $oldReferrer, ['effective_from' => today()->subMonth()]);

    $newReferral = referralService()->change($customer, $newReferrer, [
        'effective_from' => today(),
        'reason' => 'Customer requested new referrer',
    ], User::factory()->create()->id);

    expect($oldReferral->refresh()->status)->toBe(CustomerReferralStatus::ENDED)
        ->and($oldReferral->referrer_id)->toBe($oldReferrer->id)
        ->and($oldReferral->effective_to)->not->toBeNull()
        ->and($newReferral->referrer_id)->toBe($newReferrer->id)
        ->and($customer->referrals()->count())->toBe(2)
        ->and($customer->primaryReferral?->referrer_id)->toBe($newReferrer->id);
});

it('requires reasons for end and cancel and preserves rows', function (): void {
    $referral = referralService()->assign(Customer::factory()->create(), Referrer::factory()->create(), ['effective_from' => today()]);

    expect(fn () => referralService()->end($referral, ''))
        ->toThrow(DomainException::class, 'An end reason is required');

    referralService()->end($referral, 'Relationship expired');

    expect($referral->refresh()->status)->toBe(CustomerReferralStatus::ENDED)
        ->and($referral->end_reason)->toBe('Relationship expired')
        ->and(CustomerReferral::query()->whereKey($referral->id)->exists())->toBeTrue();

    $second = referralService()->assign(Customer::factory()->create(), Referrer::factory()->create(), ['effective_from' => today()]);

    expect(fn () => referralService()->cancel($second, ''))
        ->toThrow(DomainException::class, 'A cancellation reason is required');

    referralService()->cancel($second, 'Duplicate entry');

    expect($second->refresh()->status)->toBe(CustomerReferralStatus::CANCELLED)
        ->and($second->cancellation_reason)->toBe('Duplicate entry');
});

it('suspends and reactivates only when no other active primary exists', function (): void {
    $customer = Customer::factory()->create();
    $referral = referralService()->assign($customer, Referrer::factory()->create(), ['effective_from' => today()]);

    referralService()->suspend($referral, 'Review required');

    expect($referral->refresh()->status)->toBe(CustomerReferralStatus::SUSPENDED)
        ->and($customer->refresh()->primaryReferral)->toBeNull();

    $replacement = referralService()->assign($customer, Referrer::factory()->create(), ['effective_from' => today()]);

    expect(fn () => referralService()->reactivate($referral))
        ->toThrow(DomainException::class, 'Customer already has an active primary referrer');

    referralService()->end($replacement, 'Remove replacement');
    referralService()->reactivate($referral);

    expect($referral->refresh()->status)->toBe(CustomerReferralStatus::ACTIVE);
});

it('audits referral workflow actions', function (): void {
    $actor = User::factory()->create();
    $customer = Customer::factory()->create();
    $referral = referralService()->assign($customer, Referrer::factory()->create(), ['effective_from' => today()], $actor->id);

    referralService()->suspend($referral, 'Audit suspension', $actor->id);
    referralService()->reactivate($referral, null, $actor->id);
    referralService()->approve($referral, $actor->id);
    referralService()->cancel($referral, 'Audit cancellation', $actor->id);

    expect(AuditTrail::query()->where('auditable_type', $referral->getMorphClass())->where('action', 'assigned')->exists())->toBeTrue()
        ->and(AuditTrail::query()->where('auditable_type', $referral->getMorphClass())->where('action', 'suspended')->exists())->toBeTrue()
        ->and(AuditTrail::query()->where('auditable_type', $referral->getMorphClass())->where('action', 'reactivated')->exists())->toBeTrue()
        ->and(AuditTrail::query()->where('auditable_type', $referral->getMorphClass())->where('action', 'approved')->exists())->toBeTrue()
        ->and(AuditTrail::query()->where('auditable_type', $referral->getMorphClass())->where('action', 'cancelled')->exists())->toBeTrue();
});

it('detects referral reconciliation findings without mutating data', function (): void {
    $referral = CustomerReferral::factory()->create([
        'status' => CustomerReferralStatus::ACTIVE,
        'effective_to' => today()->subDay(),
    ]);
    $before = $referral->getAttributes();

    $this->artisan('biwms:customer-referral-reconcile --details')
        ->assertSuccessful()
        ->expectsOutputToContain('active_referral_past_effective_to');

    $after = $referral->refresh()->getAttributes();

    expect($after['updated_at'])->toBe($before['updated_at'])
        ->and($after['status'])->toBe($before['status'])
        ->and($after['customer_id'])->toBe($before['customer_id'])
        ->and($after['referrer_id'])->toBe($before['referrer_id'])
        ->and(Carbon::parse($after['effective_to'])->toDateString())->toBe(Carbon::parse($before['effective_to'])->toDateString());
});
