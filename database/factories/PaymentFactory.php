<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vendor;
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
            'party_id' => Vendor::factory(),
            'party_name' => null,
            'payment_method' => 'BANK_TRANSFER',
            'currency_id' => Currency::factory()->state([
                'code' => 'NGN',
                'description' => 'Nigerian Naira',
                'symbol' => '₦',
                'is_lcy' => true,
            ]),
            'currency_code' => null,
            'currency_factor' => 1.0,
            'payment_direction' => 'DISBURSEMENT',
            'created_by' => User::factory(),
        ];
    }

    public function configure(): static
    {
        return $this
            ->afterMaking(function (Payment $payment): void {
                $payment->party_name ??= $this->resolvePartyName($payment);
                $payment->currency_code ??= Currency::query()->find($payment->currency_id)?->code;
            })
            ->afterCreating(function (Payment $payment): void {
                $payment->forceFill([
                    'party_name' => $payment->party_name ?: $this->resolvePartyName($payment),
                    'currency_code' => $payment->currency_code ?: $payment->currency?->code,
                ])->saveQuietly();
            });
    }

    public function customerReceipt(): static
    {
        return $this->state([
            'party_type' => 'CUSTOMER',
            'party_id' => Customer::factory(),
            'payment_direction' => 'RECEIPT',
        ]);
    }

    private function resolvePartyName(Payment $payment): ?string
    {
        return match ($payment->party_type) {
            'CUSTOMER' => Customer::query()->find($payment->party_id)?->name,
            'VENDOR' => Vendor::query()->find($payment->party_id)?->vendor_name,
            default => null,
        };
    }
}
