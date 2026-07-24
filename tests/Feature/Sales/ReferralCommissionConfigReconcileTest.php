<?php

declare(strict_types=1);

use App\Enums\ReferralCommissionAssignmentStatus;
use App\Enums\ReferralCommissionMethod;
use App\Enums\ReferralCommissionPlanStatus;
use App\Enums\ReferralCommissionScope;
use App\Models\ReferralCommissionPlan;
use App\Models\Referrer;
use App\Models\ReferrerCommissionPlanAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reports invalid referral commission configuration without mutating records', function (): void {
    $plan = ReferralCommissionPlan::factory()->create([
        'status' => ReferralCommissionPlanStatus::ACTIVE,
        'commission_method' => ReferralCommissionMethod::FIXED_AMOUNT,
        'fixed_amount' => null,
        'commission_scope' => ReferralCommissionScope::SPECIFIC_ITEMS,
    ]);
    $referrer = Referrer::factory()->create(['is_active' => false]);
    $assignment = ReferrerCommissionPlanAssignment::factory()->create([
        'referrer_id' => $referrer->id,
        'referral_commission_plan_id' => $plan->id,
        'status' => ReferralCommissionAssignmentStatus::ACTIVE,
    ]);
    $before = $assignment->getAttributes();

    $this->artisan('biwms:referral-commission-config-reconcile --details')
        ->assertSuccessful()
        ->expectsOutputToContain('active_plan_invalid')
        ->expectsOutputToContain('assignment_inactive_referrer');

    expect($assignment->refresh()->getAttributes()['updated_at'])->toBe($before['updated_at']);
});
