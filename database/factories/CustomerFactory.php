<?php

namespace Database\Factories;

use App\Enums\BlockedReason;
use App\Enums\ContactRole;
use App\Enums\ContactType;
use App\Enums\CustomerType;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\CustomerPostingGroup;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $customerName = $this->faker->company();
        $generalBusinessPostingGroup = GeneralBusinessPostingGroup::factory()->create();
        $customerPostingGroup = CustomerPostingGroup::factory()->create();
        $location = Location::factory()->create();
        $contact = Contact::factory()->create([
            'name' => $customerName,
            'full_name' => $customerName,
            'company_name' => $customerName,
            'type' => ContactType::COMPANY->value,
            'role' => ContactRole::CUSTOMER->value,
            'general_business_posting_group_id' => $generalBusinessPostingGroup->id,
            'vat_bus_posting_group' => 'DOMESTIC',
        ]);

        return [
            'customer_number' => 'C-'.$this->faker->unique()->numberBetween(1000, 9999),
            'name' => $customerName,
            'address' => $this->faker->streetAddress(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'general_business_posting_group_id' => $generalBusinessPostingGroup->id,
            'customer_posting_group_id' => $customerPostingGroup->id,
            'vat_bus_posting_group' => 'DOMESTIC',
            'customer_type' => CustomerType::RETAIL,
            'location_id' => $location->id,
            'payment_terms_code' => 'IMMEDIATE',
            'credit_limit' => 500000,
            'blocked' => false,
            'blocked_reason' => BlockedReason::NONE,
            'contact_id' => $contact->id,
            'is_price_inclusive' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Customer $customer): void {
            $contact = $customer->contact;

            if (! $contact instanceof Contact) {
                return;
            }

            $contact->forceFill([
                'type' => ContactType::COMPANY,
                'role' => ContactRole::CUSTOMER,
                'general_business_posting_group_id' => $customer->general_business_posting_group_id,
                'vat_bus_posting_group' => $contact->vat_bus_posting_group ?: $customer->vat_bus_posting_group,
            ])->saveQuietly();
        });
    }

    public function customerWithLedgerHistory(): static
    {
        return $this->afterCreating(function (Customer $customer): void {
            $user = User::factory()->create();

            CustomerLedgerEntry::query()->create([
                'entry_number' => 1,
                'customer_id' => $customer->id,
                'document_type' => 'SALES_INVOICE',
                'document_number' => 'INV-'.$customer->id.'-001',
                'description' => 'Seeded sales invoice',
                'posting_date' => now()->subDays(10),
                'document_date' => now()->subDays(10),
                'due_date' => now()->addDays(20),
                'debit_amount' => 37670.40,
                'credit_amount' => 0,
                'amount' => 37670.40,
                'running_balance' => 37670.40,
                'remaining_amount' => 7670.40,
                'open' => true,
                'fully_applied' => false,
                'currency_code' => 'NGN',
                'original_debit_amount' => 37670.40,
                'original_credit_amount' => 0,
                'currency_factor' => 1,
                'general_business_posting_group_id' => $customer->general_business_posting_group_id,
                'customer_posting_group_id' => $customer->customer_posting_group_id,
                'source_type' => Customer::class,
                'source_id' => $customer->id,
                'created_by' => $user->id,
            ]);

            CustomerLedgerEntry::query()->create([
                'entry_number' => 2,
                'customer_id' => $customer->id,
                'document_type' => 'PAYMENT',
                'document_number' => 'PAY-'.$customer->id.'-001',
                'description' => 'Seeded customer payment',
                'posting_date' => now()->subDays(3),
                'document_date' => now()->subDays(3),
                'debit_amount' => 0,
                'credit_amount' => 30000,
                'amount' => -30000,
                'running_balance' => 7670.40,
                'remaining_amount' => 0,
                'open' => false,
                'fully_applied' => true,
                'currency_code' => 'NGN',
                'original_debit_amount' => 0,
                'original_credit_amount' => 30000,
                'currency_factor' => 1,
                'general_business_posting_group_id' => $customer->general_business_posting_group_id,
                'customer_posting_group_id' => $customer->customer_posting_group_id,
                'applied_to_entries' => [[
                    'document_number' => 'INV-'.$customer->id.'-001',
                    'amount' => 30000,
                    'entry_number' => 1,
                ]],
                'source_type' => Customer::class,
                'source_id' => $customer->id,
                'created_by' => $user->id,
            ]);
        });
    }
}
