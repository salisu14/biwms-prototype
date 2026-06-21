<?php

namespace Database\Seeders;

use App\Models\GeneralProductPostingGroup;
use App\Models\VatProductPostingGroup;
use Illuminate\Database\Seeder;

class GeneralProductPostingGroupSeeder extends Seeder
{
    public function run(): void
    {
        // ✅ Resolve VAT groups once (performance + clarity)
        $vatGroups = VatProductPostingGroup::pluck('id', 'code');

        $postingGroups = [
            [
                'code' => 'RAWMAT',
                'description' => 'Raw Materials',
                'vat_code' => 'STANDARD',
            ],
            [
                'code' => 'WIP',
                'description' => 'Work in Process',
                'vat_code' => 'STANDARD',
            ],
            [
                'code' => 'RETAIL',
                'description' => 'Retail Finished Goods',
                'vat_code' => 'STANDARD',
            ],
            [
                'code' => 'WHOLESALE',
                'description' => 'Wholesale Finished Goods',
                'vat_code' => 'STANDARD',
            ],
            [
                'code' => 'CAPACITY',
                'description' => 'Capacity/Labor',
                'vat_code' => 'ZERO',
            ],
            [
                'code' => 'SERVICE',
                'description' => 'Services',
                'vat_code' => 'STANDARD',
            ],
            [
                'code' => 'FINISHED',
                'description' => 'Finished Goods',
                'vat_code' => 'STANDARD',
            ],
            [
                'code' => 'SEMI-FINISHED',
                'description' => 'Semi-Finished Goods',
                'vat_code' => 'STANDARD',
            ],
            [
                'code' => 'CONSUMABLES',
                'description' => 'Consumables/Supplies',
                'vat_code' => 'STANDARD',
            ],
            [
                'code' => 'PACKAGING',
                'description' => 'Packaging Materials',
                'vat_code' => 'STANDARD',
            ],
            [
                'code' => 'ZERO-RATED',
                'description' => 'Zero-Rated Products',
                'vat_code' => 'ZERO',
            ],
            [
                'code' => 'EXEMPT',
                'description' => 'VAT Exempt Products',
                'vat_code' => 'ZERO', // ⚠️ you don’t have EXEMPT yet → fallback
            ],
            [
                'code' => 'REVERSE-CHARGE',
                'description' => 'Reverse Charge Products',
                'vat_code' => 'STANDARD', // ⚠️ fallback unless you define REVERSE
            ],
            [
                'code' => 'EXPENSE',
                'description' => 'Expense/Non-Inventory Items',
                'vat_code' => 'ZERO',
            ],
        ];

        foreach ($postingGroups as $group) {

            $vatId = $vatGroups[$group['vat_code']] ?? null;

            GeneralProductPostingGroup::updateOrCreate(
                ['code' => $group['code']],
                [
                    'description' => $group['description'],
                    'default_vat_product_posting_group_id' => $vatId, // ✅ FIXED
                    'auto_create_vat_prod_posting_group' => false,
                    'blocked' => false,
                ]
            );
        }
    }
}
