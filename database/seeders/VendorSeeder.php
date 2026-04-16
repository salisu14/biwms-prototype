<?php

namespace Database\Seeders;

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\VatBusinessPostingGroup;
use App\Models\Vendor;
use App\Models\VendorPostingGroup;
use Illuminate\Database\Seeder;

class VendorSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure posting groups and chart of accounts exist
        $this->ensurePostingGroupsExist();

        // Get posting groups
        $domesticBusGroup = GeneralBusinessPostingGroup::where('code', 'DOMESTIC')->first();
        $foreignBusGroup = GeneralBusinessPostingGroup::where('code', 'FOREIGN')->first();
        $euBusGroup = GeneralBusinessPostingGroup::where('code', 'EU')->first();

        $domesticVendGroup = VendorPostingGroup::where('code', 'DOMESTIC')->first();
        $foreignVendGroup = VendorPostingGroup::where('code', 'FOREIGN')->first();

        $domesticVatBusGroup = VatBusinessPostingGroup::where('code', 'DOMESTIC')->first();
        $exportVatBusGroup = VatBusinessPostingGroup::where('code', 'EXPORT')->first();

        $vendors = [
            [
                'vendor_code' => 'V0001',
                'vendor_name' => 'Alpha Chemicals Ltd',
                'contact_person' => 'John Smith',
                'email' => 'john.smith@alphachem.com',
                'phone' => '+1-555-0101',
                'mobile' => '+1-555-0199',
                'address' => '123 Industrial Parkway, Building 4',
                'city' => 'Houston',
                'state' => 'Texas',
                'postal_code' => '77001',
                'country' => 'USA',
                'tax_id' => 'TX-987654321',
                'payment_terms' => 'Net 30',
                'currency' => 'USD',
                'lead_time_days' => 14,
                'minimum_order_amount' => 500.00,
                'is_active' => true,
                'notes' => 'Preferred supplier for raw chemicals. ISO 9001 certified.',
                'general_business_posting_group_id' => $domesticBusGroup?->id,
                'vendor_posting_group_id' => $domesticVendGroup?->id,
                'vat_bus_posting_group' => 'DOMESTIC',
                'vat_business_posting_group_id' => $domesticVatBusGroup?->id,
                'payment_terms_code' => 'NET30',
                'blocked' => false,
                'blocked_reason' => 'NONE',
            ],
            [
                'vendor_code' => 'V0002',
                'vendor_name' => 'Beta Supplies Inc',
                'contact_person' => 'Sarah Johnson',
                'email' => 'sarah.j@betasupplies.com',
                'phone' => '+1-555-0202',
                'mobile' => '+1-555-0299',
                'address' => '456 Commerce Street, Suite 200',
                'city' => 'Chicago',
                'state' => 'Illinois',
                'postal_code' => '60601',
                'country' => 'USA',
                'tax_id' => 'IL-123456789',
                'payment_terms' => 'Net 15',
                'currency' => 'USD',
                'lead_time_days' => 7,
                'minimum_order_amount' => 250.00,
                'is_active' => true,
                'notes' => 'Fast delivery for packaging materials.',
                'general_business_posting_group_id' => $domesticBusGroup?->id,
                'vendor_posting_group_id' => $domesticVendGroup?->id,
                'vat_bus_posting_group' => 'DOMESTIC',
                'vat_business_posting_group_id' => $domesticVatBusGroup?->id,
                'payment_terms_code' => 'NET15',
                'blocked' => false,
                'blocked_reason' => 'NONE',
            ],
            [
                'vendor_code' => 'V0003',
                'vendor_name' => 'Gamma International',
                'contact_person' => 'Michael Chen',
                'email' => 'mchen@gammaintl.com',
                'phone' => '+86-21-5550-3030',
                'mobile' => '+86-138-0000-0001',
                'address' => '789 Pudong Avenue, Floor 15',
                'city' => 'Shanghai',
                'state' => null,
                'postal_code' => '200120',
                'country' => 'China',
                'tax_id' => 'CN-91310000XXXXXXXX',
                'payment_terms' => 'Net 60',
                'currency' => 'CNY',
                'lead_time_days' => 45,
                'minimum_order_amount' => 5000.00,
                'is_active' => true,
                'notes' => 'Overseas supplier for specialized equipment. Long lead times but competitive pricing.',
                'general_business_posting_group_id' => $foreignBusGroup?->id,
                'vendor_posting_group_id' => $foreignVendGroup?->id,
                'vat_bus_posting_group' => 'EXPORT',
                'vat_business_posting_group_id' => $exportVatBusGroup?->id,
                'payment_terms_code' => 'NET60',
                'blocked' => false,
                'blocked_reason' => 'NONE',
            ],
            [
                'vendor_code' => 'V0004',
                'vendor_name' => 'Delta Packaging Solutions',
                'contact_person' => 'Emily Rodriguez',
                'email' => 'emily.r@deltapack.com',
                'phone' => '+1-555-0404',
                'mobile' => '+1-555-0499',
                'address' => '321 Box Factory Road',
                'city' => 'Phoenix',
                'state' => 'Arizona',
                'postal_code' => '85001',
                'country' => 'USA',
                'tax_id' => 'AZ-456789123',
                'payment_terms' => 'Net 30',
                'currency' => 'USD',
                'lead_time_days' => 10,
                'minimum_order_amount' => 100.00,
                'is_active' => true,
                'notes' => 'Custom packaging solutions. Eco-friendly options available.',
                'general_business_posting_group_id' => $domesticBusGroup?->id,
                'vendor_posting_group_id' => $domesticVendGroup?->id,
                'vat_bus_posting_group' => 'DOMESTIC',
                'vat_business_posting_group_id' => $domesticVatBusGroup?->id,
                'payment_terms_code' => 'NET30',
                'blocked' => false,
                'blocked_reason' => 'NONE',
            ],
            [
                'vendor_code' => 'V0005',
                'vendor_name' => 'Epsilon Raw Materials',
                'contact_person' => 'Robert Williams',
                'email' => 'r.williams@epsilonraw.com',
                'phone' => '+1-555-0505',
                'mobile' => '+1-555-0599',
                'address' => '159 Mine Road, Industrial Zone',
                'city' => 'Denver',
                'state' => 'Colorado',
                'postal_code' => '80201',
                'country' => 'USA',
                'tax_id' => 'CO-789123456',
                'payment_terms' => 'Net 45',
                'currency' => 'USD',
                'lead_time_days' => 21,
                'minimum_order_amount' => 1000.00,
                'is_active' => true,
                'notes' => 'Bulk raw material supplier. Volume discounts available.',
                'general_business_posting_group_id' => $domesticBusGroup?->id,
                'vendor_posting_group_id' => $domesticVendGroup?->id,
                'vat_bus_posting_group' => 'DOMESTIC',
                'vat_business_posting_group_id' => $domesticVatBusGroup?->id,
                'payment_terms_code' => 'NET45',
                'blocked' => false,
                'blocked_reason' => 'NONE',
            ],
            [
                'vendor_code' => 'V0006',
                'vendor_name' => 'Zeta European Imports',
                'contact_person' => 'Anna Schmidt',
                'email' => 'anna.schmidt@zetaimports.eu',
                'phone' => '+49-30-5550-6060',
                'mobile' => '+49-170-0000-0002',
                'address' => 'Hauptstraße 42',
                'city' => 'Berlin',
                'state' => null,
                'postal_code' => '10115',
                'country' => 'Germany',
                'tax_id' => 'DE-123456789012',
                'payment_terms' => 'Net 30',
                'currency' => 'EUR',
                'lead_time_days' => 30,
                'minimum_order_amount' => 2000.00,
                'is_active' => true,
                'notes' => 'Premium European quality products. Higher cost but excellent quality.',
                'general_business_posting_group_id' => $euBusGroup?->id,
                'vendor_posting_group_id' => $foreignVendGroup?->id,
                'vat_bus_posting_group' => 'EU',
                'payment_terms_code' => 'NET30',
                'blocked' => false,
                'blocked_reason' => 'NONE',
            ],
            [
                'vendor_code' => 'V0007',
                'vendor_name' => 'Eta Logistics & Supply',
                'contact_person' => 'David Kim',
                'email' => 'david.kim@etalogistics.com',
                'phone' => '+1-555-0707',
                'mobile' => '+1-555-0799',
                'address' => '753 Distribution Center Blvd',
                'city' => 'Atlanta',
                'state' => 'Georgia',
                'postal_code' => '30301',
                'country' => 'USA',
                'tax_id' => 'GA-321654987',
                'payment_terms' => 'Net 15',
                'currency' => 'USD',
                'lead_time_days' => 5,
                'minimum_order_amount' => 100.00,
                'is_active' => true,
                'notes' => 'Emergency supplier for rush orders. Premium pricing for speed.',
                'general_business_posting_group_id' => $domesticBusGroup?->id,
                'vendor_posting_group_id' => $domesticVendGroup?->id,
                'vat_bus_posting_group' => 'DOMESTIC',
                'vat_business_posting_group_id' => $domesticVatBusGroup?->id,
                'payment_terms_code' => 'NET15',
                'blocked' => false,
                'blocked_reason' => 'NONE',
            ],
            [
                'vendor_code' => 'V0008',
                'vendor_name' => 'Theta Scientific Supplies',
                'contact_person' => 'Dr. Lisa Anderson',
                'email' => 'l.anderson@thetasci.com',
                'phone' => '+1-555-0808',
                'mobile' => '+1-555-0899',
                'address' => '951 Research Park Drive',
                'city' => 'Boston',
                'state' => 'Massachusetts',
                'postal_code' => '02101',
                'country' => 'USA',
                'tax_id' => 'MA-654321789',
                'payment_terms' => 'Net 30',
                'currency' => 'USD',
                'lead_time_days' => 12,
                'minimum_order_amount' => 750.00,
                'is_active' => true,
                'notes' => 'Laboratory grade materials. Certification documentation provided.',
                'general_business_posting_group_id' => $domesticBusGroup?->id,
                'vendor_posting_group_id' => $domesticVendGroup?->id,
                'vat_bus_posting_group' => 'DOMESTIC',
                'vat_business_posting_group_id' => $domesticVatBusGroup?->id,
                'payment_terms_code' => 'NET30',
                'blocked' => false,
                'blocked_reason' => 'NONE',
            ],
            [
                'vendor_code' => 'V0009',
                'vendor_name' => 'Iota Wholesale Distributors',
                'contact_person' => 'James Brown',
                'email' => 'jbrown@iotawholesale.com',
                'phone' => '+1-555-0909',
                'mobile' => '+1-555-0999',
                'address' => '357 Bulk Buy Lane',
                'city' => 'Dallas',
                'state' => 'Texas',
                'postal_code' => '75201',
                'country' => 'USA',
                'tax_id' => 'TX-147258369',
                'payment_terms' => 'Net 60',
                'currency' => 'USD',
                'lead_time_days' => 18,
                'minimum_order_amount' => 2500.00,
                'is_active' => true,
                'notes' => 'Wholesale pricing for high volume orders. Limited product range.',
                'general_business_posting_group_id' => $domesticBusGroup?->id,
                'vendor_posting_group_id' => $domesticVendGroup?->id,
                'vat_bus_posting_group' => 'DOMESTIC',
                'vat_business_posting_group_id' => $domesticVatBusGroup?->id,
                'payment_terms_code' => 'NET60',
                'blocked' => false,
                'blocked_reason' => 'NONE',
            ],
            [
                'vendor_code' => 'V0010',
                'vendor_name' => 'Kappa Specialty Chemicals',
                'contact_person' => 'Maria Garcia',
                'email' => 'm.garcia@kappachem.com',
                'phone' => '+1-555-1010',
                'mobile' => '+1-555-1099',
                'address' => '246 Specialty Blvd',
                'city' => 'San Diego',
                'state' => 'California',
                'postal_code' => '92101',
                'country' => 'USA',
                'tax_id' => 'CA-369258147',
                'payment_terms' => 'Net 30',
                'currency' => 'USD',
                'lead_time_days' => 28,
                'minimum_order_amount' => 1500.00,
                'is_active' => true,
                'notes' => 'Rare and specialty chemicals. Exclusive distribution rights.',
                'general_business_posting_group_id' => $domesticBusGroup?->id,
                'vendor_posting_group_id' => $domesticVendGroup?->id,
                'vat_bus_posting_group' => 'DOMESTIC',
                'vat_business_posting_group_id' => $domesticVatBusGroup?->id,
                'payment_terms_code' => 'NET30',
                'blocked' => false,
                'blocked_reason' => 'NONE',
            ],
            // Inactive vendor for testing
            [
                'vendor_code' => 'V0011',
                'vendor_name' => 'Lambda Discontinued Supplies',
                'contact_person' => 'Inactive Contact',
                'email' => 'inactive@lambda.com',
                'phone' => '+1-555-1111',
                'mobile' => null,
                'address' => '999 Closed Street',
                'city' => 'Detroit',
                'state' => 'Michigan',
                'postal_code' => '48201',
                'country' => 'USA',
                'tax_id' => 'MI-000000000',
                'payment_terms' => 'Net 30',
                'currency' => 'USD',
                'lead_time_days' => null,
                'minimum_order_amount' => null,
                'is_active' => false,
                'notes' => 'Vendor no longer active. Keep for historical records only.',
                'general_business_posting_group_id' => $domesticBusGroup?->id,
                'vendor_posting_group_id' => $domesticVendGroup?->id,
                'vat_bus_posting_group' => 'DOMESTIC',
                'vat_business_posting_group_id' => $domesticVatBusGroup?->id,
                'payment_terms_code' => 'NET30',
                'blocked' => true,
                'blocked_reason' => 'INACTIVE',
            ],
        ];

        $contactSeeder = new ContactSeeder;

        foreach ($vendors as $vendor) {

            $contact = $contactSeeder->createFromVendor($vendor);

            Vendor::firstOrCreate(
                ['vendor_code' => $vendor['vendor_code']],
                [
                    ...$vendor,
                    'contact_id' => $contact->id, // 🔥 KEY LINK
                ]
            );
        }

        $this->command->info('Vendors seeded successfully!');
        $this->command->info('Total: '.count($vendors).' vendors');
        $this->command->info('Active: '.collect($vendors)->where('is_active', true)->count());
        $this->command->info('Inactive: '.collect($vendors)->where('is_active', false)->count());
    }

    private function ensurePostingGroupsExist(): void
    {
        // Create Chart of Accounts for payables if they don't exist
        $payablesDomestic = ChartOfAccount::firstOrCreate(
            ['account_number' => '21100'],
            [
                'name' => 'Trade Payables - Domestic',
                'account_type' => AccountType::LIABILITY,
                'account_category' => AccountCategory::PAYABLE,
            ]
        );

        $payablesForeign = ChartOfAccount::firstOrCreate(
            ['account_number' => '21200'],
            [
                'name' => 'Trade Payables - Foreign',
                'account_type' => AccountType::LIABILITY,
                'account_category' => AccountCategory::PAYABLE,
            ]
        );

        $discountDebit = ChartOfAccount::firstOrCreate(
            ['account_number' => '50900'],
            [
                'name' => 'Purchase Discounts',
                'account_type' => AccountType::EXPENSE,
                'account_category' => AccountCategory::COGS,
            ]
        );

        $discountCredit = ChartOfAccount::firstOrCreate(
            ['account_number' => '40900'],
            [
                'name' => 'Sales Discounts',
                'account_type' => AccountType::REVENUE,
                'account_category' => AccountCategory::REVENUE,
            ]
        );

        $rounding = ChartOfAccount::firstOrCreate(
            ['account_number' => '60950'],
            [
                'name' => 'Invoice Rounding',
                'account_type' => AccountType::REVENUE,
                'account_category' => AccountCategory::OPERATING_EXPENSE,
            ]
        );

        // Create General Business Posting Groups
        $generalGroups = [
            ['code' => 'DOMESTIC', 'description' => 'Domestic Vendors'],
            ['code' => 'FOREIGN', 'description' => 'Foreign Vendors'],
            ['code' => 'EU', 'description' => 'European Union'],
        ];

        foreach ($generalGroups as $group) {
            GeneralBusinessPostingGroup::firstOrCreate(
                ['code' => $group['code']],
                $group
            );
        }

        // Create Vendor Posting Groups with foreign keys
        $vendorGroups = [
            [
                'code' => 'DOMESTIC',
                'description' => 'Domestic Vendors',
                'payables_account_id' => $payablesDomestic->id,
                'payment_disc_debit_account_id' => $discountDebit->id,
                'payment_disc_credit_account_id' => $discountCredit->id,
                'invoice_rounding_account_id' => $rounding->id,
            ],
            [
                'code' => 'FOREIGN',
                'description' => 'Foreign Vendors',
                'payables_account_id' => $payablesForeign->id,
                'payment_disc_debit_account_id' => $discountDebit->id,
                'payment_disc_credit_account_id' => $discountCredit->id,
                'invoice_rounding_account_id' => $rounding->id,
            ],
        ];

        foreach ($vendorGroups as $group) {
            VendorPostingGroup::firstOrCreate(
                ['code' => $group['code']],
                $group
            );
        }
    }
}
