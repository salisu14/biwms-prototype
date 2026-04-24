<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\VatBusinessPostingGroup;
use App\Models\VatPostingSetup;
use App\Models\VatProductPostingGroup;
use Illuminate\Database\Seeder;

class VatPostingSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Business Groups
        $domestic = VatBusinessPostingGroup::firstOrCreate(
            ['code' => 'DOMESTIC'],
            ['description' => 'Domestic Customers and Vendors']
        );

        $export = VatBusinessPostingGroup::firstOrCreate(
            ['code' => 'EXPORT'],
            ['description' => 'Export Customers and Vendors']
        );

        // 2. Product Groups
        $standard = VatProductPostingGroup::firstOrCreate(
            ['code' => 'STANDARD'],
            ['description' => 'Standard Rate VAT (15%)']
        );

        $reduced = VatProductPostingGroup::firstOrCreate(
            ['code' => 'REDUCED'],
            ['description' => 'Reduced Rate VAT (5%)']
        );

        $zero = VatProductPostingGroup::firstOrCreate(
            ['code' => 'ZERO'],
            ['description' => 'Zero Rate VAT (0%)']
        );

        $exempt = VatProductPostingGroup::firstOrCreate(
            ['code' => 'EXEMPT'],
            ['description' => 'VAT Exempt']
        );

        $reverse = VatProductPostingGroup::firstOrCreate(
            ['code' => 'REVERSE'],
            ['description' => 'Reverse Charge VAT']
        );

        // 3. Accounts
        $salesVatAcc = ChartOfAccount::where('account_number', '20100')->first();
        $purchaseVatAcc = ChartOfAccount::where('account_number', '14100')->first();

        // 4. Helper function to reduce repetition
        $setup = function ($business, $product, $percent) use ($salesVatAcc, $purchaseVatAcc) {
            VatPostingSetup::updateOrCreate(
                [
                    'vat_business_posting_group_id' => $business->id,
                    'vat_product_posting_group_id' => $product->id,
                    'vat_calculation_type' => 'normal', // ✅ ONLY VALID VALUE
                ],
                [
                    'vat_percent' => $percent,
                    'sales_vat_account_id' => $salesVatAcc?->id,
                    'purchase_vat_account_id' => $purchaseVatAcc?->id,
                ]
            );
        };

        // 5. Valid combinations only
        $setup($domestic, $standard, 15.00);
        $setup($domestic, $reduced, 5.00);
        $setup($domestic, $zero, 0.00);

        $setup($export, $standard, 0.00);
        $setup($export, $zero, 0.00);

        // Optional: treat exempt & reverse as ZERO-rated logic
        $setup($domestic, $exempt, 0.00);
        $setup($domestic, $reverse, 0.00);
    }
}
