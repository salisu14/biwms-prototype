<?php

declare(strict_types=1);

use App\Enums\ReferralCommissionPlanStatus;
use App\Models\Business;
use App\Models\ReferralCommissionPlan;
use App\Services\Sales\ReferralCommissions\ReferralCommissionSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function referralCommissionBusiness(string $code = 'BIZ-RC'): Business
{
    return Business::query()->create([
        'code' => $code,
        'name' => "{$code} Business",
        'is_active' => true,
    ]);
}

it('stores one disabled referral commission setting per business', function (): void {
    $business = referralCommissionBusiness();

    $setting = app(ReferralCommissionSettingService::class)->saveForBusiness($business, [
        'is_enabled' => false,
        'require_plan_assignment' => true,
        'commission_decimal_places' => 4,
    ]);

    $updated = app(ReferralCommissionSettingService::class)->saveForBusiness($business, [
        'is_enabled' => true,
        'require_plan_assignment' => true,
        'commission_decimal_places' => 2,
    ]);

    expect($setting->id)->toBe($updated->id)
        ->and($updated->is_enabled)->toBeTrue()
        ->and($updated->commission_decimal_places)->toBe(2);
});

it('validates default plan business status decimals and minimum amount', function (): void {
    $business = referralCommissionBusiness('BIZ-A');
    $otherBusiness = referralCommissionBusiness('BIZ-B');
    $activePlan = ReferralCommissionPlan::factory()->active()->create(['business_id' => $business->id]);
    $otherPlan = ReferralCommissionPlan::factory()->active()->create(['business_id' => $otherBusiness->id]);
    $draftPlan = ReferralCommissionPlan::factory()->create(['business_id' => $business->id, 'status' => ReferralCommissionPlanStatus::DRAFT]);
    $service = app(ReferralCommissionSettingService::class);

    expect(fn () => $service->saveForBusiness($business, ['default_plan_id' => $otherPlan->id, 'commission_decimal_places' => 4]))
        ->toThrow(DomainException::class, 'same business');

    expect(fn () => $service->saveForBusiness($business, ['default_plan_id' => $draftPlan->id, 'commission_decimal_places' => 4]))
        ->toThrow(DomainException::class, 'must be active');

    expect(fn () => $service->saveForBusiness($business, ['default_plan_id' => $activePlan->id, 'commission_decimal_places' => 7]))
        ->toThrow(DomainException::class, 'between 0 and 6');

    expect(fn () => $service->saveForBusiness($business, ['default_plan_id' => $activePlan->id, 'commission_decimal_places' => 4, 'minimum_eligible_sale_amount' => -1]))
        ->toThrow(DomainException::class, 'cannot be negative');
});
