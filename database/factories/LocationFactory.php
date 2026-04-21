<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->lexify('LOC-???'),
            'name' => $this->faker->city,
            'is_active' => true,
            'blocked' => false,
        ];
    }
}
