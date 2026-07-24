<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ReferralCommissionPlan;
use App\Models\ReferralCommissionPlanTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferralCommissionPlanTier>
 */
class ReferralCommissionPlanTierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'referral_commission_plan_id' => ReferralCommissionPlan::factory(),
            'sequence' => $this->faker->unique()->numberBetween(1, 999),
            'minimum_threshold' => 0,
            'maximum_threshold' => null,
            'percentage_rate' => 5,
        ];
    }
}
