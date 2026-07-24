<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReferralCommissionAssignmentStatus;
use App\Models\ReferralCommissionPlan;
use App\Models\Referrer;
use App\Models\ReferrerCommissionPlanAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferrerCommissionPlanAssignment>
 */
class ReferrerCommissionPlanAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'referrer_id' => Referrer::factory(),
            'referral_commission_plan_id' => ReferralCommissionPlan::factory()->active(),
            'status' => ReferralCommissionAssignmentStatus::ACTIVE,
            'effective_from' => today(),
            'effective_to' => null,
            'is_primary' => true,
            'assigned_at' => now(),
        ];
    }
}
