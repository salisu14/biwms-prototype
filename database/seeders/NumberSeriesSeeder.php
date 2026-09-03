<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Services\Manufacturing\ProductionOrderNumberSeriesSetupService;
use App\Services\Sales\ReferralCommissions\ReferralCommissionPlanNumberSeriesSetupService;
use App\Services\Sales\ReferrerNumberSeriesSetupService;
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
                'starting_number' => 1,
                'ending_number' => 999999,
                'current_number' => 0,
                'year' => 2026,
                'module' => 'finance',
            ],
            [
                'code' => 'PC-TRANS',
                'description' => 'Petty Cash Transactions',
                'prefix' => 'PCT',
                'starting_number' => 1,
                'ending_number' => 999999,
                'current_number' => 0,
                'year' => 2026,
                'module' => 'finance',
            ],
            [
                'code' => 'PAYMENT',
                'description' => 'Payments',
                'prefix' => 'PAY',
                'starting_number' => 1,
                'ending_number' => 999999,
                'current_number' => 0,
                'year' => 2026,
                'module' => 'finance',
            ],
            [
                'code' => 'CUSTOMER-OPENING',
                'description' => 'Customer Opening Balances',
                'prefix' => 'COB',
                'starting_number' => 1,
                'ending_number' => 999999,
                'current_number' => 0,
                'year' => 2026,
                'module' => 'finance',
            ],
            [
                'code' => 'VENDOR-OPENING',
                'description' => 'Vendor Opening Balances',
                'prefix' => 'VOB',
                'starting_number' => 1,
                'ending_number' => 999999,
                'current_number' => 0,
                'year' => 2026,
                'module' => 'finance',
            ],
            [
                'code' => 'COMM-LIAB',
                'description' => 'Commission Liability Postings',
                'prefix' => 'CLP',
                'starting_number' => 1,
                'ending_number' => 999999,
                'current_number' => 0,
                'year' => 2026,
                'module' => 'sales',
            ],
            [
                'code' => 'COMM-PAY',
                'description' => 'Commission Payment Batches',
                'prefix' => 'CPB',
                'starting_number' => 1,
                'ending_number' => 999999,
                'current_number' => 0,
                'year' => 2026,
                'module' => 'sales',
            ],
        ];

        foreach ($series as $s) {
            $numberSeries = NumberSeries::firstOrCreate(['code' => $s['code']], $s);

            if (in_array($numberSeries->code, ['CUSTOMER-OPENING', 'VENDOR-OPENING'], true)) {
                NumberSeriesLine::firstOrCreate(
                    ['number_series_id' => $numberSeries->id, 'starting_date' => '2026-01-01'],
                    [
                        'starting_no' => 0,
                        'ending_no' => 999999,
                        'increment_by' => 1,
                        'last_no_used' => 0,
                        'no_of_digits' => 5,
                        'prefix' => $numberSeries->prefix,
                        'blocked' => false,
                    ],
                );
            }
        }

        app(ProductionOrderNumberSeriesSetupService::class)->ensure();
        app(ReferrerNumberSeriesSetupService::class)->ensure();
        app(ReferralCommissionPlanNumberSeriesSetupService::class)->ensure();
    }
}
