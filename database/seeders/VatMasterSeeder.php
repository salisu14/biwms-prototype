<?php

// database/seeders/VatMasterSeeder.php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\VatMaster;
use Illuminate\Database\Seeder;

class VatMasterSeeder extends Seeder
{
    public function run(): void
    {
        $purchaseVatAccount = ChartOfAccount::where('account_number', '14100')->first();
        $salesVatAccount = ChartOfAccount::where('account_number', '20100')->first();

        // Fallback for safety if COA seeder hasn't run or is different
        $purchaseAccountId = $purchaseVatAccount?->id;
        $salesAccountId = $salesVatAccount?->id;

        $vatRates = [
            [
                'code' => 'NO VAT',
                'description' => 'Zero-rated / Exempt',
                'purchase_account_id' => $purchaseAccountId,
                'sales_account_id' => $salesAccountId,
                'percentage' => 0,
            ],
            [
                'code' => 'VAT 7.5',
                'description' => 'Standard VAT Rate (7.5%)',
                'purchase_account_id' => $purchaseAccountId,
                'sales_account_id' => $salesAccountId,
                'percentage' => 7.5,
            ],
            [
                'code' => 'VAT 10',
                'description' => 'Higher VAT Rate (10%)',
                'purchase_account_id' => $purchaseAccountId,
                'sales_account_id' => $salesAccountId,
                'percentage' => 10,
            ],
        ];

        foreach ($vatRates as $vat) {
            VatMaster::updateOrCreate(['code' => $vat['code']], $vat);
        }
    }
}
