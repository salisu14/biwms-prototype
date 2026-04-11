<?php

namespace Database\Factories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_number' => 'PAY-'.$this->faker->unique()->numberBetween(1000, 9999),
            'payment_date' => now(),
            'posting_date' => now(),
            'status' => 'PENDING',
            'payment_amount' => 0,
            'party_type' => 'VENDOR',
            'party_id' => 1,
            'currency_id' => 1,
            'currency_code' => 'USD',
            'currency_factor' => 1.0,
            'payment_direction' => 'DISBURSEMENT',
        ];
    }
}
