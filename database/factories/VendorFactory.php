<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContactRole;
use App\Enums\ContactType;
use App\Models\Contact;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPostingGroup;
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
        $companyName = $this->faker->company();
        $generalBusinessPostingGroup = GeneralBusinessPostingGroup::factory()->create();
        $vendorPostingGroup = VendorPostingGroup::factory()->create();
        $contact = Contact::factory()->create([
            'name' => $companyName,
            'full_name' => $companyName,
            'company_name' => $companyName,
            'type' => ContactType::COMPANY->value,
            'role' => ContactRole::VENDOR->value,
            'general_business_posting_group_id' => $generalBusinessPostingGroup->id,
            'vendor_posting_group_id' => $vendorPostingGroup->id,
            'vat_bus_posting_group' => 'DOMESTIC',
        ]);

        return [
            'vendor_code' => 'V-'.$this->faker->unique()->numerify('##########'),
            'vendor_name' => $companyName,
            'contact_person' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'postal_code' => $this->faker->postcode(),
            'country' => $this->faker->countryCode(),
            'is_active' => true,
            'blocked' => false,
            'general_business_posting_group_id' => $generalBusinessPostingGroup->id,
            'vendor_posting_group_id' => $vendorPostingGroup->id,
            'gen_bus_posting_group' => $generalBusinessPostingGroup->code,
            'vendor_posting_group' => $vendorPostingGroup->code,
            'vat_bus_posting_group' => 'DOMESTIC',
            'contact_id' => $contact->id,
            'is_price_inclusive' => false,
        ];
    }

    public function configure(): static
    {
        return $this
            ->afterMaking(function (Vendor $vendor): void {
                $generalBusinessPostingGroup = GeneralBusinessPostingGroup::query()
                    ->find($vendor->general_business_posting_group_id);
                $vendorPostingGroup = VendorPostingGroup::query()
                    ->find($vendor->vendor_posting_group_id);

                $vendor->gen_bus_posting_group ??= $generalBusinessPostingGroup?->code;
                $vendor->vendor_posting_group ??= $vendorPostingGroup?->code;
            })
            ->afterCreating(function (Vendor $vendor): void {
                $generalBusinessPostingGroup = $vendor->generalBusinessPostingGroup;
                $vendorPostingGroup = $vendor->vendorPostingGroup;
                $contact = $vendor->contact;

                if ($contact instanceof Contact) {
                    $contact->forceFill([
                        'company_name' => $contact->company_name ?: $vendor->vendor_name,
                        'type' => ContactType::COMPANY,
                        'role' => ContactRole::VENDOR,
                        'vendor_posting_group_id' => $vendorPostingGroup?->id,
                        'general_business_posting_group_id' => $generalBusinessPostingGroup?->id,
                        'vat_bus_posting_group' => $contact->vat_bus_posting_group ?: $vendor->vat_bus_posting_group,
                    ])->saveQuietly();
                }

                $vendor->forceFill([
                    'gen_bus_posting_group' => $vendor->gen_bus_posting_group ?: $generalBusinessPostingGroup?->code,
                    'vendor_posting_group' => $vendor->vendor_posting_group ?: $vendorPostingGroup?->code,
                ])->saveQuietly();
            });
    }

    public function vendorWithLedgerHistory(): static
    {
        return $this->afterCreating(function (Vendor $vendor): void {
            $user = User::factory()->create();

            VendorLedgerEntry::query()->create([
                'entry_number' => 1,
                'vendor_id' => $vendor->id,
                'document_type' => 'PURCHASE_INVOICE',
                'document_number' => 'PINV-'.$vendor->id.'-001',
                'description' => 'Seeded purchase invoice',
                'posting_date' => now()->subDays(10),
                'document_date' => now()->subDays(10),
                'due_date' => now()->addDays(20),
                'debit_amount' => 250000,
                'credit_amount' => 0,
                'amount' => 250000,
                'running_balance' => 250000,
                'remaining_amount' => 50000,
                'open' => true,
                'fully_applied' => false,
                'currency_code' => 'NGN',
                'original_debit_amount' => 250000,
                'original_credit_amount' => 0,
                'currency_factor' => 1,
                'general_business_posting_group_id' => $vendor->general_business_posting_group_id,
                'vendor_posting_group_id' => $vendor->vendor_posting_group_id,
                'source_type' => Vendor::class,
                'source_id' => $vendor->id,
                'created_by' => $user->id,
            ]);

            VendorLedgerEntry::query()->create([
                'entry_number' => 2,
                'vendor_id' => $vendor->id,
                'document_type' => 'PAYMENT',
                'document_number' => 'VPAY-'.$vendor->id.'-001',
                'description' => 'Seeded vendor payment',
                'posting_date' => now()->subDays(3),
                'document_date' => now()->subDays(3),
                'debit_amount' => 0,
                'credit_amount' => 200000,
                'amount' => -200000,
                'running_balance' => 50000,
                'remaining_amount' => 0,
                'open' => false,
                'fully_applied' => true,
                'currency_code' => 'NGN',
                'original_debit_amount' => 0,
                'original_credit_amount' => 200000,
                'currency_factor' => 1,
                'general_business_posting_group_id' => $vendor->general_business_posting_group_id,
                'vendor_posting_group_id' => $vendor->vendor_posting_group_id,
                'applied_to_entries' => [[
                    'document_number' => 'PINV-'.$vendor->id.'-001',
                    'amount' => 200000,
                    'entry_number' => 1,
                ]],
                'source_type' => Vendor::class,
                'source_id' => $vendor->id,
                'created_by' => $user->id,
            ]);
        });
    }
}
