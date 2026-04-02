<?php

namespace Database\Seeders;

use App\Models\GeneralBusinessPostingGroup;
use Illuminate\Database\Seeder;

class GeneralBusinessPostingGroupSeeder extends Seeder
{
    public function run(): void
    {
        $postingGroups = [
            // Domestic / Local Market
            [
                'code' => 'DOMESTIC',
                'description' => 'Domestic Customers/Vendors',
                'default_vat_bus_posting_group' => 'DOMESTIC',
                'auto_create_vat_bus_posting_group' => false,
            ],
            [
                'code' => 'LOCAL',
                'description' => 'Local Market',
                'default_vat_bus_posting_group' => 'DOMESTIC',
                'auto_create_vat_bus_posting_group' => false,
            ],

            // Export / International
            [
                'code' => 'EXPORT',
                'description' => 'Export Sales/Purchases',
                'default_vat_bus_posting_group' => 'EXPORT',
                'auto_create_vat_bus_posting_group' => false,
            ],
            [
                'code' => 'FOREIGN',
                'description' => 'Foreign Customers/Vendors',
                'default_vat_bus_posting_group' => 'EXPORT',
                'auto_create_vat_bus_posting_group' => false,
            ],

            // European Union
            [
                'code' => 'EU',
                'description' => 'European Union',
                'default_vat_bus_posting_group' => 'EU',
                'auto_create_vat_bus_posting_group' => false,
            ],
            [
                'code' => 'EU-EXEMPT',
                'description' => 'EU VAT Exempt',
                'default_vat_bus_posting_group' => 'EU-EXEMPT',
                'auto_create_vat_bus_posting_group' => false,
            ],

            // Special Tax Zones
            [
                'code' => 'TAX-EXEMPT',
                'description' => 'Tax Exempt Customers/Vendors',
                'default_vat_bus_posting_group' => 'ZERO',
                'auto_create_vat_bus_posting_group' => false,
            ],
            [
                'code' => 'NON-TAXABLE',
                'description' => 'Non-Taxable Transactions',
                'default_vat_bus_posting_group' => 'ZERO',
                'auto_create_vat_bus_posting_group' => false,
            ],

            // Industry Specific
            [
                'code' => 'INTERCOMPANY',
                'description' => 'Intercompany Transactions',
                'default_vat_bus_posting_group' => 'INTERCO',
                'auto_create_vat_bus_posting_group' => false,
            ],
            [
                'code' => 'INTERNAL',
                'description' => 'Internal Use Only',
                'default_vat_bus_posting_group' => 'ZERO',
                'auto_create_vat_bus_posting_group' => false,
            ],

            // Retail vs Wholesale
            [
                'code' => 'RETAIL',
                'description' => 'Retail Customers',
                'default_vat_bus_posting_group' => 'DOMESTIC',
                'auto_create_vat_bus_posting_group' => false,
            ],
            [
                'code' => 'WHOLESALE',
                'description' => 'Wholesale/B2B Customers',
                'default_vat_bus_posting_group' => 'DOMESTIC',
                'auto_create_vat_bus_posting_group' => false,
            ],

            // Government & Special Entities
            [
                'code' => 'GOVERNMENT',
                'description' => 'Government Entities',
                'default_vat_bus_posting_group' => 'GOVT',
                'auto_create_vat_bus_posting_group' => false,
            ],
            [
                'code' => 'NON-PROFIT',
                'description' => 'Non-Profit Organizations',
                'default_vat_bus_posting_group' => 'NONPROFIT',
                'auto_create_vat_bus_posting_group' => false,
            ],

            // Reverse Charge / Special Schemes
            [
                'code' => 'REVERSE-CHARGE',
                'description' => 'Reverse Charge VAT',
                'default_vat_bus_posting_group' => 'REVERSE',
                'auto_create_vat_bus_posting_group' => false,
            ],
            [
                'code' => 'MOSS',
                'description' => 'Mini One Stop Shop (EU Digital)',
                'default_vat_bus_posting_group' => 'MOSS',
                'auto_create_vat_bus_posting_group' => false,
            ],
            [
                'code' => 'DOM-VENDOR',
                'description' => 'Domestic Vendors',
                'default_vat_bus_posting_group' => 'DOMESTIC',
                'auto_create_vat_bus_posting_group' => false,
            ],
            [
                'code' => 'IMPORT-VENDOR',
                'description' => 'Import Vendors',
                'default_vat_bus_posting_group' => 'IMPORT',
                'auto_create_vat_bus_posting_group' => false,
            ],
            [
                'code' => 'MANUFACTURING',
                'description' => 'Internal Manufacturing',
                'default_vat_bus_posting_group' => 'ZERO',
                'auto_create_vat_bus_posting_group' => false,
            ],

            // Free Trade Zones
            [
                'code' => 'FTZ',
                'description' => 'Free Trade Zone',
                'default_vat_bus_posting_group' => 'FTZ',
                'auto_create_vat_bus_posting_group' => false,
            ],
            [
                'code' => 'BONDED',
                'description' => 'Bonded Warehouse',
                'default_vat_bus_posting_group' => 'BONDED',
                'auto_create_vat_bus_posting_group' => false,
            ],
        ];

        foreach ($postingGroups as $group) {
            GeneralBusinessPostingGroup::updateOrCreate(
                ['code' => $group['code']],
                $group
            );
        }
    }
}
