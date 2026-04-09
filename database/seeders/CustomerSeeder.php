<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerPostingGroup;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\Location;
use App\Models\PricingGroup;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure dependencies exist
        $this->ensureDependenciesExist();

        // Get posting groups
        $domesticBusGroup = GeneralBusinessPostingGroup::where('code', 'DOMESTIC')->first();
        $foreignBusGroup = GeneralBusinessPostingGroup::where('code', 'FOREIGN')->first();
        $euBusGroup = GeneralBusinessPostingGroup::where('code', 'EU')->first();
        $exportBusGroup = GeneralBusinessPostingGroup::where('code', 'EXPORT')->first();

        $domesticCustGroup = CustomerPostingGroup::where('code', 'DOMESTIC')->first();
        $foreignCustGroup = CustomerPostingGroup::where('code', 'FOREIGN')->first();
        $exportCustGroup = CustomerPostingGroup::where('code', 'EXPORT')->first();
        $intercompanyCustGroup = CustomerPostingGroup::where('code', 'INTERCOMPANY')->first();

        $mainLocation = Location::first();
        $standardPricingGroup = PricingGroup::where('code', 'STANDARD')->first();

        $customers = [
            // Domestic Customers
            [
                'customer_number' => 'C0001',
                'name' => 'Acme Corporation',
                'address' => '123 Business Plaza, Suite 100',
                'email' => 'purchasing@acmecorp.com',
                'phone' => '+1-555-1001',
                'general_business_posting_group_id' => $domesticBusGroup?->id,
                'customer_posting_group_id' => $domesticCustGroup?->id,
                'vat_bus_posting_group' => 'DOMESTIC',
                'location_id' => $mainLocation?->id,
                'shipping_agent_code' => 'FEDEX',
                'payment_terms_code' => 'NET30',
                'credit_limit' => 50000.00,
                'blocked' => false,
                'blocked_reason' => 'NONE',
                'pricing_group_id' => $standardPricingGroup?->id,
                'price_list_code' => null,
                'allow_discounts' => true,
                'maximum_discount_percent' => 10.00,
                'price_includes_vat' => false,
            ],
            [
                'customer_number' => 'C0002',
                'name' => 'TechStart Industries',
                'address' => '456 Innovation Drive',
                'email' => 'orders@techstart.io',
                'phone' => '+1-555-1002',
                'general_business_posting_group_id' => $domesticBusGroup?->id,
                'customer_posting_group_id' => $domesticCustGroup?->id,
                'vat_bus_posting_group' => 'DOMESTIC',
                'location_id' => $mainLocation?->id,
                'shipping_agent_code' => 'UPS',
                'payment_terms_code' => 'NET15',
                'credit_limit' => 25000.00,
                'blocked' => false,
                'blocked_reason' => 'NONE',
                'pricing_group_id' => $standardPricingGroup?->id,
                'price_list_code' => null,
                'allow_discounts' => true,
                'maximum_discount_percent' => 5.00,
                'price_includes_vat' => false,
            ],
            [
                'customer_number' => 'C0003',
                'name' => 'Global Retail Solutions',
                'address' => '789 Commerce Street',
                'email' => 'buying@globalretail.com',
                'phone' => '+1-555-1003',
                'general_business_posting_group_id' => $domesticBusGroup?->id,
                'customer_posting_group_id' => $domesticCustGroup?->id,
                'vat_bus_posting_group' => 'DOMESTIC',
                'location_id' => $mainLocation?->id,
                'shipping_agent_code' => 'FEDEX',
                'payment_terms_code' => 'NET45',
                'credit_limit' => 100000.00,
                'blocked' => false,
                'blocked_reason' => 'NONE',
                'pricing_group_id' => $standardPricingGroup?->id,
                'price_list_code' => 'RETAIL-001',
                'allow_discounts' => true,
                'maximum_discount_percent' => 15.00,
                'price_includes_vat' => false,
            ],
            [
                'customer_number' => 'C0004',
                'name' => 'Manufacturing Plus LLC',
                'address' => '321 Factory Lane',
                'email' => 'procurement@mfgplus.com',
                'phone' => '+1-555-1004',
                'general_business_posting_group_id' => $domesticBusGroup?->id,
                'customer_posting_group_id' => $domesticCustGroup?->id,
                'vat_bus_posting_group' => 'DOMESTIC',
                'location_id' => $mainLocation?->id,
                'shipping_agent_code' => 'UPS',
                'payment_terms_code' => 'NET30',
                'credit_limit' => 75000.00,
                'blocked' => false,
                'blocked_reason' => 'NONE',
                'pricing_group_id' => $standardPricingGroup?->id,
                'price_list_code' => null,
                'allow_discounts' => true,
                'maximum_discount_percent' => 8.00,
                'price_includes_vat' => false,
            ],
            [
                'customer_number' => 'C0005',
                'name' => 'City Government Procurement',
                'address' => '555 City Hall Plaza',
                'email' => 'purchasing@citygov.gov',
                'phone' => '+1-555-1005',
                'general_business_posting_group_id' => $domesticBusGroup?->id,
                'customer_posting_group_id' => $domesticCustGroup?->id,
                'vat_bus_posting_group' => 'TAX-EXEMPT',
                'location_id' => $mainLocation?->id,
                'shipping_agent_code' => 'FEDEX',
                'payment_terms_code' => 'NET60',
                'credit_limit' => 200000.00,
                'blocked' => false,
                'blocked_reason' => 'NONE',
                'pricing_group_id' => $standardPricingGroup?->id,
                'price_list_code' => 'GOVT-001',
                'allow_discounts' => false,
                'maximum_discount_percent' => 0.00,
                'price_includes_vat' => false,
            ],

            // Export Customers
            [
                'customer_number' => 'C0006',
                'name' => 'Canada Distributors Inc',
                'address' => '100 Maple Street, Toronto, ON',
                'email' => 'orders@canadadist.ca',
                'phone' => '+1-416-555-2001',
                'general_business_posting_group_id' => $exportBusGroup?->id,
                'customer_posting_group_id' => $exportCustGroup?->id,
                'vat_bus_posting_group' => 'EXPORT',
                'location_id' => $mainLocation?->id,
                'shipping_agent_code' => 'DHL',
                'payment_terms_code' => 'NET30',
                'credit_limit' => 40000.00,
                'blocked' => false,
                'blocked_reason' => 'NONE',
                'pricing_group_id' => $standardPricingGroup?->id,
                'price_list_code' => null,
                'allow_discounts' => true,
                'maximum_discount_percent' => 10.00,
                'price_includes_vat' => false,
            ],
            [
                'customer_number' => 'C0007',
                'name' => 'Mexico Industrial Supply',
                'address' => 'Av. Reforma 500, Mexico City',
                'email' => 'compras@mexindustrial.mx',
                'phone' => '+52-55-555-3001',
                'general_business_posting_group_id' => $exportBusGroup?->id,
                'customer_posting_group_id' => $exportCustGroup?->id,
                'vat_bus_posting_group' => 'EXPORT',
                'location_id' => $mainLocation?->id,
                'shipping_agent_code' => 'DHL',
                'payment_terms_code' => 'NET45',
                'credit_limit' => 30000.00,
                'blocked' => false,
                'blocked_reason' => 'NONE',
                'pricing_group_id' => $standardPricingGroup?->id,
                'price_list_code' => null,
                'allow_discounts' => true,
                'maximum_discount_percent' => 12.00,
                'price_includes_vat' => false,
            ],

            // EU Customers
            [
                'customer_number' => 'C0008',
                'name' => 'Deutschland Technik GmbH',
                'address' => 'Industriestraße 50, Munich',
                'email' => 'einkauf@dtech.de',
                'phone' => '+49-89-555-4001',
                'general_business_posting_group_id' => $euBusGroup?->id,
                'customer_posting_group_id' => $foreignCustGroup?->id,
                'vat_bus_posting_group' => 'EU',
                'location_id' => $mainLocation?->id,
                'shipping_agent_code' => 'DHL',
                'payment_terms_code' => 'NET30',
                'credit_limit' => 60000.00,
                'blocked' => false,
                'blocked_reason' => 'NONE',
                'pricing_group_id' => $standardPricingGroup?->id,
                'price_list_code' => null,
                'allow_discounts' => true,
                'maximum_discount_percent' => 10.00,
                'price_includes_vat' => false,
            ],
            [
                'customer_number' => 'C0009',
                'name' => 'France Commerce SARL',
                'address' => '25 Rue de la Paix, Paris',
                'email' => 'achats@frcommerce.fr',
                'phone' => '+33-1-555-5001',
                'general_business_posting_group_id' => $euBusGroup?->id,
                'customer_posting_group_id' => $foreignCustGroup?->id,
                'vat_bus_posting_group' => 'EU',
                'location_id' => $mainLocation?->id,
                'shipping_agent_code' => 'DHL',
                'payment_terms_code' => 'NET30',
                'credit_limit' => 35000.00,
                'blocked' => false,
                'blocked_reason' => 'NONE',
                'pricing_group_id' => $standardPricingGroup?->id,
                'price_list_code' => null,
                'allow_discounts' => true,
                'maximum_discount_percent' => 8.00,
                'price_includes_vat' => true,
            ],

            // Foreign (Non-EU) Customers
            [
                'customer_number' => 'C0010',
                'name' => 'UK Trading Partners Ltd',
                'address' => '10 Downing Street, London',
                'email' => 'buying@uktrading.co.uk',
                'phone' => '+44-20-555-6001',
                'general_business_posting_group_id' => $foreignBusGroup?->id,
                'customer_posting_group_id' => $foreignCustGroup?->id,
                'vat_bus_posting_group' => 'FOREIGN',
                'location_id' => $mainLocation?->id,
                'shipping_agent_code' => 'DHL',
                'payment_terms_code' => 'NET30',
                'credit_limit' => 45000.00,
                'blocked' => false,
                'blocked_reason' => 'NONE',
                'pricing_group_id' => $standardPricingGroup?->id,
                'price_list_code' => null,
                'allow_discounts' => true,
                'maximum_discount_percent' => 10.00,
                'price_includes_vat' => false,
            ],
            [
                'customer_number' => 'C0011',
                'name' => 'Japan Precision Tools KK',
                'address' => '1-1-1 Otemachi, Chiyoda-ku, Tokyo',
                'email' => 'ordering@jptools.jp',
                'phone' => '+81-3-555-7001',
                'general_business_posting_group_id' => $foreignBusGroup?->id,
                'customer_posting_group_id' => $foreignCustGroup?->id,
                'vat_bus_posting_group' => 'FOREIGN',
                'location_id' => $mainLocation?->id,
                'shipping_agent_code' => 'DHL',
                'payment_terms_code' => 'NET60',
                'credit_limit' => 80000.00,
                'blocked' => false,
                'blocked_reason' => 'NONE',
                'pricing_group_id' => $standardPricingGroup?->id,
                'price_list_code' => null,
                'allow_discounts' => true,
                'maximum_discount_percent' => 5.00,
                'price_includes_vat' => false,
            ],
            [
                'customer_number' => 'C0012',
                'name' => 'Australia Mining Supplies',
                'address' => '50 Collins Street, Melbourne',
                'email' => 'orders@ausmining.com.au',
                'phone' => '+61-3-555-8001',
                'general_business_posting_group_id' => $foreignBusGroup?->id,
                'customer_posting_group_id' => $foreignCustGroup?->id,
                'vat_bus_posting_group' => 'FOREIGN',
                'location_id' => $mainLocation?->id,
                'shipping_agent_code' => 'DHL',
                'payment_terms_code' => 'NET45',
                'credit_limit' => 55000.00,
                'blocked' => false,
                'blocked_reason' => 'NONE',
                'pricing_group_id' => $standardPricingGroup?->id,
                'price_list_code' => null,
                'allow_discounts' => true,
                'maximum_discount_percent' => 10.00,
                'price_includes_vat' => false,
            ],

            // Intercompany
            [
                'customer_number' => 'C0099',
                'name' => 'Sister Company Division',
                'address' => 'Internal Address',
                'email' => 'interco@company.com',
                'phone' => null,
                'general_business_posting_group_id' => $domesticBusGroup?->id,
                'customer_posting_group_id' => $intercompanyCustGroup?->id,
                'vat_bus_posting_group' => 'INTERCO',
                'location_id' => $mainLocation?->id,
                'shipping_agent_code' => 'INTERNAL',
                'payment_terms_code' => 'NET0',
                'credit_limit' => null,
                'blocked' => false,
                'blocked_reason' => 'NONE',
                'pricing_group_id' => $standardPricingGroup?->id,
                'price_list_code' => 'INTERCO-STD',
                'allow_discounts' => false,
                'maximum_discount_percent' => 0.00,
                'price_includes_vat' => false,
            ],

            // Blocked Customer
            [
                'customer_number' => 'C0098',
                'name' => 'Bad Debt Trading Co',
                'address' => 'Unknown Address',
                'email' => 'noreply@baddebt.com',
                'phone' => null,
                'general_business_posting_group_id' => $domesticBusGroup?->id,
                'customer_posting_group_id' => $domesticCustGroup?->id,
                'vat_bus_posting_group' => 'DOMESTIC',
                'location_id' => $mainLocation?->id,
                'shipping_agent_code' => null,
                'payment_terms_code' => 'PREPAID',
                'credit_limit' => 0.00,
                'blocked' => true,
                'blocked_reason' => 'PAYMENT',
                'pricing_group_id' => $standardPricingGroup?->id,
                'price_list_code' => null,
                'allow_discounts' => false,
                'maximum_discount_percent' => 0.00,
                'price_includes_vat' => false,
            ],
        ];

        $contactSeeder = new ContactSeeder;

        foreach ($customers as $customer) {

            $contact = $contactSeeder->createFromCustomer($customer);

            Customer::firstOrCreate(
                ['customer_number' => $customer['customer_number']],
                [
                    ...$customer,
                    'contact_id' => $contact->id, // 🔥 KEY LINK
                ]
            );
        }

        $this->command->info('Customers seeded successfully!');
        $this->command->info('Total: '.count($customers).' customers');
        $this->command->info('Active: '.collect($customers)->where('blocked', false)->count());
        $this->command->info('Blocked: '.collect($customers)->where('blocked', true)->count());
    }

    private function ensureDependenciesExist(): void
    {
        // Create General Business Posting Groups if not exist
        $generalGroups = [
            ['code' => 'DOMESTIC', 'description' => 'Domestic Customers'],
            ['code' => 'FOREIGN', 'description' => 'Foreign Customers'],
            ['code' => 'EU', 'description' => 'European Union'],
            ['code' => 'EXPORT', 'description' => 'Export Customers'],
        ];

        foreach ($generalGroups as $group) {
            GeneralBusinessPostingGroup::firstOrCreate(
                ['code' => $group['code']],
                $group
            );
        }

        // Create Pricing Group if not exist
        PricingGroup::firstOrCreate(
            ['code' => 'STANDARD'],
            ['name' => 'Standard Pricing', 'description' => 'Default pricing group']
        );

        // Create default location if not exist
        if (! Location::exists()) {
            Location::create([
                'code' => 'MAIN',
                'name' => 'Main Warehouse',
                'address' => '123 Main Street',
                'city' => 'New York',
                'is_active' => true,
            ]);
        }

        // Run Customer Posting Group Seeder if needed
        if (CustomerPostingGroup::count() === 0) {
            $this->call(CustomerPostingGroupSeeder::class);
        }
    }
}
