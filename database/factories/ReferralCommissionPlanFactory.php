<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReferralCommissionBasis;
use App\Enums\ReferralCommissionMethod;
use App\Enums\ReferralCommissionPlanStatus;
use App\Enums\ReferralCommissionScope;
use App\Models\ReferralCommissionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferralCommissionPlan>
 */
class ReferralCommissionPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'RCP-'.$this->faker->unique()->numerify('######'),
            'name' => $this->faker->words(3, true),
            'status' => ReferralCommissionPlanStatus::DRAFT,
            'commission_basis' => ReferralCommissionBasis::POSTED_SALES,
            'commission_method' => ReferralCommissionMethod::PERCENTAGE,
            'commission_scope' => ReferralCommissionScope::ALL_ELIGIBLE_SALES,
            'percentage_rate' => 5,
            'effective_from' => today(),
            'priority' => 100,
            'is_default' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => ReferralCommissionPlanStatus::ACTIVE,
            'activated_at' => now(),
        ]);
    }
}
