<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_code' => 'V-'.$this->faker->unique()->numberBetween(1000, 9999),
            'vendor_name' => $this->faker->company(),
            'is_active' => true,
            'blocked' => false,
        ];
    }
}
