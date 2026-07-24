<?php

declare(strict_types=1);

use App\Enums\ReferralCommissionMethod;
use App\Enums\ReferralCommissionScope;
use App\Enums\ReferralCommissionTierBasis;
use App\Models\Currency;
use App\Models\NumberSeriesLine;
use App\Models\ReferralCommissionPlan;
use App\Models\ReferralCommissionPlanTier;
use App\Services\Sales\ReferralCommissions\ReferralCommissionPlanNumberSeriesSetupService;
use App\Services\Sales\ReferralCommissions\ReferralCommissionPlanService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(ReferralCommissionPlanNumberSeriesSetupService::class)->ensure();
});

it('creates plan codes from number series without consuming numbers on invalid creation', function (): void {
    $line = NumberSeriesLine::query()->whereHas('series', fn ($query) => $query->where('code', ReferralCommissionPlanNumberSeriesSetupService::CODE))->firstOrFail();

    expect($line->last_no_used)->toBe(0);

    expect(fn () => app(ReferralCommissionPlanService::class)->create([
        'name' => 'Invalid Percentage Plan',
        'commission_method' => ReferralCommissionMethod::PERCENTAGE,
        'effective_from' => today(),
    ]))->toThrow(DomainException::class);

    expect($line->refresh()->last_no_used)->toBe(0);

    $plan = app(ReferralCommissionPlanService::class)->create([
        'name' => 'Valid Percentage Plan',
        'commission_method' => ReferralCommissionMethod::PERCENTAGE,
        'percentage_rate' => 5,
        'effective_from' => today(),
    ]);

    expect($plan->code)->toBe('RCP-000001')
        ->and($line->refresh()->last_no_used)->toBe(1);
});

it('validates method consistency and activation requirements', function (): void {
    $currency = Currency::factory()->create();
    $service = app(ReferralCommissionPlanService::class);

    expect(fn () => $service->create([
        'name' => 'Bad Percentage',
        'commission_method' => ReferralCommissionMethod::PERCENTAGE,
        'percentage_rate' => 101,
        'effective_from' => today(),
    ]))->toThrow(DomainException::class, 'no more than 100');

    expect(fn () => $service->create([
        'name' => 'Bad Fixed',
        'commission_method' => ReferralCommissionMethod::FIXED_AMOUNT,
        'effective_from' => today(),
        'currency_id' => $currency->id,
    ]))->toThrow(DomainException::class, 'Fixed amount plans require');

    $tiered = $service->create([
        'name' => 'Tiered Plan',
        'commission_method' => ReferralCommissionMethod::TIERED_PERCENTAGE,
        'tier_basis' => ReferralCommissionTierBasis::SALES_AMOUNT,
        'effective_from' => today(),
    ]);

    expect(fn () => $service->activate($tiered))
        ->toThrow(DomainException::class, 'at least one tier');
});

it('prevents duplicate tier sequences at the database level', function (): void {
    $plan = ReferralCommissionPlan::factory()->create([
        'commission_method' => ReferralCommissionMethod::TIERED_PERCENTAGE,
        'tier_basis' => ReferralCommissionTierBasis::SALES_AMOUNT,
    ]);

    ReferralCommissionPlanTier::factory()->create([
        'referral_commission_plan_id' => $plan->id,
        'sequence' => 1,
        'minimum_threshold' => 0,
        'maximum_threshold' => 100,
        'percentage_rate' => 5,
    ]);

    expect(fn () => ReferralCommissionPlanTier::factory()->create([
        'referral_commission_plan_id' => $plan->id,
        'sequence' => 1,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('prevents overlapping tiers before activation', function (): void {
    $plan = ReferralCommissionPlan::factory()->create([
        'commission_method' => ReferralCommissionMethod::TIERED_PERCENTAGE,
        'tier_basis' => ReferralCommissionTierBasis::SALES_AMOUNT,
    ]);

    ReferralCommissionPlanTier::factory()->create([
        'referral_commission_plan_id' => $plan->id,
        'sequence' => 1,
        'minimum_threshold' => 0,
        'maximum_threshold' => 100,
        'percentage_rate' => 5,
    ]);

    ReferralCommissionPlanTier::factory()->create([
        'referral_commission_plan_id' => $plan->id,
        'sequence' => 2,
        'minimum_threshold' => 50,
        'maximum_threshold' => 200,
        'percentage_rate' => 7,
    ]);

    expect(fn () => app(ReferralCommissionPlanService::class)->activate($plan->refresh()))
        ->toThrow(DomainException::class, 'cannot overlap');
});

it('requires eligibility rows for scoped plans before activation', function (): void {
    $plan = ReferralCommissionPlan::factory()->create([
        'commission_scope' => ReferralCommissionScope::SPECIFIC_ITEMS,
    ]);

    expect(fn () => app(ReferralCommissionPlanService::class)->activate($plan))
        ->toThrow(DomainException::class, 'at least one included item');
});
