<?php

namespace Database\Factories\Manufacturing;

use App\Models\Manufacturing\WorkCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkCenter>
 */
class WorkCenterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->bothify('WC-####'),
            'name' => $this->faker->words(2, true),
            'efficiency' => 100,
            'direct_unit_cost' => 50,
        ];
    }
}
