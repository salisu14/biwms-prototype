<?php

namespace Database\Factories\Manufacturing;

use App\Models\Manufacturing\CapExProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CapExProject>
 */
class CapExProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_number' => $this->faker->unique()->bothify('CAPEX-####'),
            'description' => $this->faker->words(3, true),
            'budget_amount' => 5000,
            'actual_amount' => 0,
            'status' => 'APPROVED',
            'project_manager_id' => \App\Models\User::factory(),
            'wip_gl_account_id' => \App\Models\ChartOfAccount::factory(),
            'capex_gl_account_id' => \App\Models\ChartOfAccount::factory(),
            'created_by' => \App\Models\User::factory(),
            'last_modified_by' => \App\Models\User::factory(),
            'approver_id' => \App\Models\User::factory(),
        ];
    }
}
