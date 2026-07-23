<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReferrerType;
use App\Models\Referrer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Referrer>
 */
class ReferrerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'REF-'.$this->faker->unique()->numberBetween(100000, 999999),
            'name' => $this->faker->name(),
            'type' => ReferrerType::INDIVIDUAL,
            'phone' => $this->faker->optional()->phoneNumber(),
            'email' => $this->faker->optional()->safeEmail(),
            'address' => $this->faker->optional()->streetAddress(),
            'city' => $this->faker->optional()->city(),
            'state' => $this->faker->optional()->state(),
            'country' => $this->faker->optional()->country(),
            'commission_eligible' => true,
            'is_active' => true,
        ];
    }
}
