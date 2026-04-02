<?php

namespace Database\Seeders;

use App\Models\GeneralProductPostingGroup;
use Illuminate\Database\Seeder;

class GeneralProductPostingGroupSeeder extends Seeder
{
    public function run(): void
    {
        $postingGroups = [
            // Core Inventory Groups
            [
                'code' => 'RAWMAT',
                'description' => 'Raw Materials',
                'default_vat_prod_posting_group' => 'STANDARD',
                'auto_create_vat_prod_posting_group' => false,
            ],
            [
                'code' => 'WIP',
                'description' => 'Work in Process',
                'default_vat_prod_posting_group' => 'STANDARD',
                'auto_create_vat_prod_posting_group' => false,
            ],
            [
                'code' => 'RETAIL',
                'description' => 'Retail Finished Goods',
                'default_vat_prod_posting_group' => 'STANDARD',
                'auto_create_vat_prod_posting_group' => false,
            ],
            [
                'code' => 'WHOLESALE',
                'description' => 'Wholesale Finished Goods',
                'default_vat_prod_posting_group' => 'STANDARD',
                'auto_create_vat_prod_posting_group' => false,
            ],

            // Manufacturing / Capacity
            [
                'code' => 'CAPACITY',
                'description' => 'Capacity/Labor',
                'default_vat_prod_posting_group' => 'ZERO',
                'auto_create_vat_prod_posting_group' => false,
            ],
            [
                'code' => 'SERVICE',
                'description' => 'Services',
                'default_vat_prod_posting_group' => 'STANDARD',
                'auto_create_vat_prod_posting_group' => false,
            ],

            // Special Categories
            [
                'code' => 'FINISHED',
                'description' => 'Finished Goods',
                'default_vat_prod_posting_group' => 'STANDARD',
                'auto_create_vat_prod_posting_group' => false,
            ],
            [
                'code' => 'SEMI-FINISHED',
                'description' => 'Semi-Finished Goods',
                'default_vat_prod_posting_group' => 'STANDARD',
                'auto_create_vat_prod_posting_group' => false,
            ],
            [
                'code' => 'CONSUMABLES',
                'description' => 'Consumables/Supplies',
                'default_vat_prod_posting_group' => 'STANDARD',
                'auto_create_vat_prod_posting_group' => false,
            ],
            [
                'code' => 'PACKAGING',
                'description' => 'Packaging Materials',
                'default_vat_prod_posting_group' => 'STANDARD',
                'auto_create_vat_prod_posting_group' => false,
            ],

            // VAT Special Categories
            [
                'code' => 'ZERO-RATED',
                'description' => 'Zero-Rated Products',
                'default_vat_prod_posting_group' => 'ZERO',
                'auto_create_vat_prod_posting_group' => false,
            ],
            [
                'code' => 'EXEMPT',
                'description' => 'VAT Exempt Products',
                'default_vat_prod_posting_group' => 'EXEMPT',
                'auto_create_vat_prod_posting_group' => false,
            ],
            [
                'code' => 'REVERSE-CHARGE',
                'description' => 'Reverse Charge Products',
                'default_vat_prod_posting_group' => 'REVERSE',
                'auto_create_vat_prod_posting_group' => false,
            ],
        ];

        foreach ($postingGroups as $group) {
            GeneralProductPostingGroup::updateOrCreate(
                ['code' => $group['code']],
                $group
            );
        }
    }
}
