<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CustomerReferralStatus;
use App\Models\Customer;
use App\Models\CustomerReferral;
use App\Models\Referrer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerReferral>
 */
class CustomerReferralFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'referrer_id' => Referrer::factory(),
            'status' => CustomerReferralStatus::ACTIVE,
            'is_primary' => true,
            'referred_at' => today(),
            'effective_from' => today(),
            'effective_to' => null,
            'referral_source' => $this->faker->optional()->word(),
            'reference' => $this->faker->optional()->bothify('REFSRC-####'),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
