<?php

namespace Database\Seeders;

use App\Models\NumberSeries;
use Illuminate\Database\Seeder;

class NumberSeriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $series = [
            [
                'code' => 'PURCHASE',
                'description' => 'Purchase Orders',
                'prefix' => 'P',
                'starting_number' => 1,
                'ending_number' => 99999,
                'current_number' => 0,
                'year' => 2026,
                'module' => 'purchase',
            ],
            [
                'code' => 'PURCHASE_RETURN',
                'description' => 'Purchase Returns',
                'prefix' => 'PR',
                'starting_number' => 1,
                'ending_number' => 99999,
                'current_number' => 0,
                'year' => 2026,
                'module' => 'purchase',
            ],
            [
                'code' => 'PURCHASE_INVOICE',
                'description' => 'Purchase Invoices',
                'prefix' => 'PI',
                'starting_number' => 1,
                'ending_number' => 99999,
                'current_number' => 0,
                'year' => 2026,
                'module' => 'purchase',
            ],
            [
                'code' => 'CUSTOMER',
                'description' => 'Customer Series',
                'prefix' => 'CUS',
                'starting_number' => 1000,
                'ending_number' => 99999,
                'current_number' => 0,
                'year' => 2026,
                'module' => 'sales',
            ],
            [
                'code' => 'VENDOR',
                'description' => 'Vendor Series',
                'prefix' => 'VEN',
                'starting_number' => 1000,
                'ending_number' => 99999,
                'current_number' => 0,
                'year' => 2026,
                'module' => 'purchase',
            ],
            [
                'code' => 'PC-VOUCHER',
                'description' => 'Petty Cash Vouchers',
                'prefix' => 'PCV',
                'padding' => 6
            ],
            [
                'code' => 'PC-TRANS',
                'description' => 'Petty Cash Transactions',
                'prefix' => 'PCT',
                'padding'  => 6
            ],
        ];

        foreach ($series as $s) {
            NumberSeries::firstOrCreate(['code' => $s['code']], $s);
        }
    }
}
