<?php
// database/seeders/VatMasterSeeder.php

namespace Database\Seeders;

use App\Models\VatMaster;
use Illuminate\Database\Seeder;

class VatMasterSeeder extends Seeder
{
    public function run(): void
    {
        $vatRates = [
            [
                'code' => 'no',
                'description' => 'no vat',
                'purchase_account_number' => 'GL-1001',
                'sales_account_number' => 'GL-1002',
                'percentage' => 0,
            ],
            [
                'code' => 'VAT 7',
                'description' => 'VAT 7.5',
                'purchase_account_number' => 'GL-1001',
                'sales_account_number' => 'GL-1002',
                'percentage' => 7.5,
            ],
            [
                'code' => 'VAT 10',
                'description' => 'VAT 10',
                'purchase_account_number' => 'GL-1002',
                'sales_account_number' => 'GL-1003',
                'percentage' => 10,
            ],
        ];

        foreach ($vatRates as $vat) {
            VatMaster::firstOrCreate(['code' => $vat['code']], $vat);
        }
    }
}
