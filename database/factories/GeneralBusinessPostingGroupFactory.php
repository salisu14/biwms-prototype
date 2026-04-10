<?php

namespace Database\Factories;

use App\Models\GeneralBusinessPostingGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class GeneralBusinessPostingGroupFactory extends Factory
{
    protected $model = GeneralBusinessPostingGroup::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->lexify('DOM-???'),
            'description' => $this->faker->sentence(),
            'default_vat_bus_posting_group' => 'DOMESTIC',
            'blocked' => false,
        ];
    }
}
