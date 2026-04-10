<?php

namespace Database\Factories;

use App\Models\VendorPostingGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class VendorPostingGroupFactory extends Factory
{
    protected $model = VendorPostingGroup::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->lexify('VEND-???'),
            'description' => $this->faker->sentence(),
            'payables_account' => $this->faker->numerify('21###'),
            'blocked' => false,
        ];
    }
}
