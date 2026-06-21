<?php

namespace Database\Seeders;

use App\Enums\AccountScheduleAmountType;
use App\Enums\AccountScheduleRowType;
use App\Enums\AccountScheduleTotalingType;
use App\Models\AccountSchedule;
use Illuminate\Database\Seeder;

class BalanceSheetAccountScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schedule = AccountSchedule::query()->updateOrCreate(
            ['name' => 'Default Balance Sheet'],
            ['description' => 'BC-style default balance sheet layout']
        );

        $lines = [
            ['line_no' => 10000, 'row_no' => 'H1', 'description' => 'ASSETS', 'totaling_type' => AccountScheduleTotalingType::COMMENT, 'totaling' => null, 'bold' => true, 'indentation' => 0],
            ['line_no' => 11000, 'row_no' => 'A1', 'description' => 'Current Assets', 'totaling_type' => AccountScheduleTotalingType::TOTAL_ACCOUNTS, 'totaling' => '10000..14999', 'bold' => true, 'indentation' => 1],
            ['line_no' => 12000, 'row_no' => 'A2', 'description' => 'Non-Current Assets', 'totaling_type' => AccountScheduleTotalingType::TOTAL_ACCOUNTS, 'totaling' => '15000..19999', 'bold' => true, 'indentation' => 1],
            ['line_no' => 13000, 'row_no' => 'AT', 'description' => 'Total Assets', 'totaling_type' => AccountScheduleTotalingType::FORMULA, 'totaling' => 'A1 + A2', 'bold' => true, 'indentation' => 0],

            ['line_no' => 20000, 'row_no' => 'H2', 'description' => 'LIABILITIES', 'totaling_type' => AccountScheduleTotalingType::COMMENT, 'totaling' => null, 'bold' => true, 'indentation' => 0],
            ['line_no' => 21000, 'row_no' => 'L1', 'description' => 'Current Liabilities', 'totaling_type' => AccountScheduleTotalingType::TOTAL_ACCOUNTS, 'totaling' => '20000..24999', 'bold' => true, 'indentation' => 1],
            ['line_no' => 22000, 'row_no' => 'L2', 'description' => 'Non-Current Liabilities', 'totaling_type' => AccountScheduleTotalingType::TOTAL_ACCOUNTS, 'totaling' => '25000..29999', 'bold' => true, 'indentation' => 1],
            ['line_no' => 23000, 'row_no' => 'LT', 'description' => 'Total Liabilities', 'totaling_type' => AccountScheduleTotalingType::FORMULA, 'totaling' => 'L1 + L2', 'bold' => true, 'indentation' => 0],

            ['line_no' => 30000, 'row_no' => 'H3', 'description' => 'EQUITY', 'totaling_type' => AccountScheduleTotalingType::COMMENT, 'totaling' => null, 'bold' => true, 'indentation' => 0],
            ['line_no' => 31000, 'row_no' => 'E1', 'description' => 'Total Equity', 'totaling_type' => AccountScheduleTotalingType::TOTAL_ACCOUNTS, 'totaling' => '30000..39999', 'bold' => true, 'indentation' => 1],

            ['line_no' => 40000, 'row_no' => 'LE', 'description' => 'Total Liabilities + Equity', 'totaling_type' => AccountScheduleTotalingType::FORMULA, 'totaling' => 'LT + E1', 'bold' => true, 'indentation' => 0],
            ['line_no' => 41000, 'row_no' => 'CHK', 'description' => 'Balance Check (Assets - L+E)', 'totaling_type' => AccountScheduleTotalingType::FORMULA, 'totaling' => 'AT - LE', 'bold' => true, 'indentation' => 0],
        ];

        foreach ($lines as $line) {
            $schedule->lines()->updateOrCreate(
                ['line_no' => $line['line_no']],
                [
                    'row_no' => $line['row_no'],
                    'description' => $line['description'],
                    'totaling_type' => $line['totaling_type'],
                    'totaling' => $line['totaling'],
                    'row_type' => AccountScheduleRowType::BALANCE_AT_DATE,
                    'amount_type' => AccountScheduleAmountType::NET_AMOUNT,
                    'show_opposite_sign' => false,
                    'bold' => $line['bold'],
                    'italic' => false,
                    'underline' => false,
                    'indentation' => $line['indentation'],
                    'new_page' => false,
                ]
            );
        }
    }
}
