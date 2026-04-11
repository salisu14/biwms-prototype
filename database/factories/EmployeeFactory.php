<?php

namespace Database\Factories;

use App\Enums\EmployeeAssignmentType;
use App\Models\Employee;
use App\Models\EmployeePostingGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_number' => $this->faker->unique()->numerify('EMP-####'),
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail,
            'phone' => $this->faker->phoneNumber,
            'job_title' => $this->faker->jobTitle,
            'assignment_type' => EmployeeAssignmentType::Corporate,
            'employee_posting_group_id' => EmployeePostingGroup::factory(),
            'business_code' => null,
            'factory_code' => null,
            'department_code' => null,
            'is_active' => true,
        ];
    }
}
