<?php

declare(strict_types=1);

use App\Enums\ReferralCommissionAssignmentStatus;
use App\Models\Business;
use App\Models\ReferralCommissionPlan;
use App\Models\Referrer;
use App\Models\ReferrerCommissionPlanAssignment;
use App\Services\Sales\ReferralCommissions\ReferrerCommissionPlanAssignmentService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('assigns active plans to active commission eligible referrers and blocks invalid assignments', function (): void {
    $business = Business::query()->create(['code' => 'BIZ-A', 'name' => 'Business A', 'is_active' => true]);
    $otherBusiness = Business::query()->create(['code' => 'BIZ-B', 'name' => 'Business B', 'is_active' => true]);
    $plan = ReferralCommissionPlan::factory()->active()->create(['business_id' => $business->id, 'effective_from' => today()]);
    $otherPlan = ReferralCommissionPlan::factory()->active()->create(['business_id' => $otherBusiness->id, 'effective_from' => today()]);
    $referrer = Referrer::factory()->create(['business_id' => $business->id]);
    $inactiveReferrer = Referrer::factory()->create(['business_id' => $business->id, 'is_active' => false]);
    $ineligibleReferrer = Referrer::factory()->create(['business_id' => $business->id, 'commission_eligible' => false]);
    $service = app(ReferrerCommissionPlanAssignmentService::class);

    $assignment = $service->assign($referrer, $plan, ['effective_from' => today()]);

    expect($assignment->status)->toBe(ReferralCommissionAssignmentStatus::ACTIVE)
        ->and($referrer->refresh()->activeCommissionPlanAssignment?->referral_commission_plan_id)->toBe($plan->id);

    expect(fn () => $service->assign($inactiveReferrer, $plan, ['effective_from' => today()]))
        ->toThrow(DomainException::class, 'Inactive referrers');

    expect(fn () => $service->assign($ineligibleReferrer, $plan, ['effective_from' => today()]))
        ->toThrow(DomainException::class, 'Commission-ineligible');

    expect(fn () => $service->assign(Referrer::factory()->create(['business_id' => $business->id]), $otherPlan, ['effective_from' => today()]))
        ->toThrow(DomainException::class, 'same business');
});

it('preserves assignment history when changing ending and cancelling', function (): void {
    $referrer = Referrer::factory()->create();
    $oldPlan = ReferralCommissionPlan::factory()->active()->create(['effective_from' => today()->subMonth()]);
    $newPlan = ReferralCommissionPlan::factory()->active()->create(['effective_from' => today()]);
    $service = app(ReferrerCommissionPlanAssignmentService::class);
    $oldAssignment = $service->assign($referrer, $oldPlan, ['effective_from' => today()->subMonth()]);

    $newAssignment = $service->change($referrer, $newPlan, [
        'effective_from' => today(),
        'reason' => 'Move to new plan',
    ]);

    expect($oldAssignment->refresh()->status)->toBe(ReferralCommissionAssignmentStatus::ENDED)
        ->and($oldAssignment->referral_commission_plan_id)->toBe($oldPlan->id)
        ->and($newAssignment->referral_commission_plan_id)->toBe($newPlan->id);

    expect(fn () => $service->end($newAssignment, ''))->toThrow(DomainException::class, 'end reason');
    $service->end($newAssignment, 'No longer eligible');
    expect($newAssignment->refresh()->status)->toBe(ReferralCommissionAssignmentStatus::ENDED);

    $cancelled = $service->assign($referrer, $oldPlan, ['effective_from' => today()->addDay()]);
    expect(fn () => $service->cancel($cancelled, ''))->toThrow(DomainException::class, 'cancellation reason');
    $service->cancel($cancelled, 'Entered in error');
    expect($cancelled->refresh()->status)->toBe(ReferralCommissionAssignmentStatus::CANCELLED);
});

it('enforces one open active primary assignment at the database level', function (): void {
    $referrer = Referrer::factory()->create();

    ReferrerCommissionPlanAssignment::factory()->create([
        'referrer_id' => $referrer->id,
        'status' => ReferralCommissionAssignmentStatus::ACTIVE,
        'is_primary' => true,
        'effective_to' => null,
    ]);

    expect(fn () => ReferrerCommissionPlanAssignment::factory()->create([
        'referrer_id' => $referrer->id,
        'status' => ReferralCommissionAssignmentStatus::ACTIVE,
        'is_primary' => true,
        'effective_to' => null,
    ]))->toThrow(UniqueConstraintViolationException::class);
});
