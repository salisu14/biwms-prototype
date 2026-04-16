<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\VatBusinessPostingGroup;
use App\Models\VatPostingSetup;
use App\Models\VatProductPostingGroup;
use Illuminate\Database\Seeder;

class VatPostingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. VAT Business Posting Groups
        $domestic = VatBusinessPostingGroup::firstOrCreate(
            ['code' => 'DOMESTIC'],
            ['description' => 'Domestic Customers and Vendors']
        );

        $export = VatBusinessPostingGroup::firstOrCreate(
            ['code' => 'EXPORT'],
            ['description' => 'Export Customers and Vendors']
        );

        // 2. VAT Product Posting Groups
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

        // 3. Resolve VAT G/L Accounts
        $salesVatAcc = ChartOfAccount::where('account_number', '20100')->first();
        $purchaseVatAcc = ChartOfAccount::where('account_number', '14100')->first();

        // 4. VAT Posting Setup Matrix
        // Domestic + Standard
        VatPostingSetup::updateOrCreate(
            [
                'vat_business_posting_group_id' => $domestic->id,
                'vat_product_posting_group_id' => $standard->id,
                'vat_calculation_type' => 'normal',
            ],
            [
                'vat_percent' => 15.00,
                'sales_vat_account_id' => $salesVatAcc?->id,
                'purchase_vat_account_id' => $purchaseVatAcc?->id,
                'vat_calculation_type' => 'normal',
            ]
        );

        // Domestic + Reduced
        VatPostingSetup::updateOrCreate(
            [
                'vat_business_posting_group_id' => $domestic->id,
                'vat_product_posting_group_id' => $reduced->id,
                'vat_calculation_type' => 'normal',
            ],
            [
                'vat_percent' => 5.00,
                'sales_vat_account_id' => $salesVatAcc?->id,
                'purchase_vat_account_id' => $purchaseVatAcc?->id,
                'vat_calculation_type' => 'normal',
            ]
        );

        // Export + Standard (Tax free for export)
        VatPostingSetup::updateOrCreate(
            [
                'vat_business_posting_group_id' => $export->id,
                'vat_product_posting_group_id' => $standard->id,
                'vat_calculation_type' => 'normal',
            ],
            [
                'vat_percent' => 0.00,
                'sales_vat_account_id' => $salesVatAcc?->id,
                'purchase_vat_account_id' => $purchaseVatAcc?->id,
                'vat_calculation_type' => 'normal',
            ]
        );

        // Domestic + Zero
        VatPostingSetup::updateOrCreate(
            [
                'vat_business_posting_group_id' => $domestic->id,
                'vat_product_posting_group_id' => $zero->id,
                'vat_calculation_type' => 'normal',
            ],
            [
                'vat_percent' => 0.00,
                'sales_vat_account_id' => $salesVatAcc?->id,
                'purchase_vat_account_id' => $purchaseVatAcc?->id,
                'vat_calculation_type' => 'normal',
            ]
        );
    }
}
