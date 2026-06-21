<?php

namespace Database\Seeders;

use App\Enums\ContactRole;
use App\Enums\ContactType;
use App\Models\Contact;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function createFromCustomer(array $customer): Contact
    {
        return Contact::firstOrCreate(
            ['email' => $customer['email']],
            [
                'name' => $customer['name'],
                'full_name' => $customer['name'],
                'company_name' => $customer['name'],

                'type' => ContactType::COMPANY,
                'role' => ContactRole::CUSTOMER,

                'email' => $customer['email'],
                'phone' => $customer['phone'] ?? null,

                'address' => $customer['address'] ?? null,
                'city' => null,
                'state' => null,
                'country' => null,

                'tax_id' => null,

                'currency_code' => null,

                'general_business_posting_group_id' => $customer['general_business_posting_group_id'] ?? null,
                'vat_bus_posting_group' => $customer['vat_bus_posting_group'] ?? null,
            ]
        );
    }

    public function createFromVendor(array $vendor): Contact
    {
        return Contact::firstOrCreate(
            ['email' => $vendor['email']],
            [
                'name' => $vendor['vendor_name'],
                'full_name' => $vendor['contact_person'] ?? $vendor['vendor_name'],
                'company_name' => $vendor['vendor_name'],

                'type' => ContactType::COMPANY,
                'role' => ContactRole::VENDOR,

                'email' => $vendor['email'],
                'phone' => $vendor['phone'] ?? null,
                'mobile' => $vendor['mobile'] ?? null,

                'address' => $vendor['address'] ?? null,
                'city' => $vendor['city'] ?? null,
                'state' => $vendor['state'] ?? null,
                'postal_code' => $vendor['postal_code'] ?? null,
                'country' => $vendor['country'] ?? null,

                'tax_id' => $vendor['tax_id'] ?? null,

                'currency' => $vendor['currency'] ?? null,

                'general_business_posting_group_id' => $vendor['general_business_posting_group_id'] ?? null,
                'vendor_posting_group_id' => $vendor['vendor_posting_group_id'] ?? null,
                'vat_bus_posting_group' => $vendor['vat_bus_posting_group'] ?? null,
            ]
        );
    }

    private function upgradeRole(Contact $contact, ContactRole $newRole): void
    {
        if ($contact->role !== $newRole) {
            $contact->role = ContactRole::BOTH;
            $contact->save();
        }
    }
}
