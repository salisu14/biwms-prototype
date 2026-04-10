<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\EmployeePostingGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeePostingGroup>
 */
class EmployeePostingGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('???')),
            'description' => $this->faker->words(3, true),
            'payables_account_id' => ChartOfAccount::factory(),
            'blocked' => false,
        ];
    }
}
