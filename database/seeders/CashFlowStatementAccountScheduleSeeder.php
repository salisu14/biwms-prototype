<?php

namespace Database\Seeders;

use App\Enums\AccountScheduleAmountType;
use App\Enums\AccountScheduleRowType;
use App\Enums\AccountScheduleTotalingType;
use App\Models\AccountSchedule;
use Illuminate\Database\Seeder;

class CashFlowStatementAccountScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schedule = AccountSchedule::query()->updateOrCreate(
            ['name' => 'Default Cash Flow Statement'],
            ['description' => 'BC-style default cash flow mapping schedule']
        );

        $lines = [
            ['line_no' => 10000, 'row_no' => 'H1', 'description' => 'OPERATING ACTIVITIES', 'totaling_type' => AccountScheduleTotalingType::COMMENT, 'totaling' => null, 'bold' => true, 'indentation' => 0],
            ['line_no' => 11000, 'row_no' => 'AR', 'description' => 'Receivables', 'totaling_type' => AccountScheduleTotalingType::TOTAL_ACCOUNTS, 'totaling' => '11100..11800', 'bold' => false, 'indentation' => 1],
            ['line_no' => 12000, 'row_no' => 'INV', 'description' => 'Inventory', 'totaling_type' => AccountScheduleTotalingType::TOTAL_ACCOUNTS, 'totaling' => '13000..14999', 'bold' => false, 'indentation' => 1],
            ['line_no' => 13000, 'row_no' => 'AP', 'description' => 'Payables', 'totaling_type' => AccountScheduleTotalingType::TOTAL_ACCOUNTS, 'totaling' => '21100..24999', 'bold' => false, 'indentation' => 1],

            ['line_no' => 20000, 'row_no' => 'H2', 'description' => 'INVESTING ACTIVITIES', 'totaling_type' => AccountScheduleTotalingType::COMMENT, 'totaling' => null, 'bold' => true, 'indentation' => 0],
            ['line_no' => 21000, 'row_no' => 'CAPEX', 'description' => 'Capital Expenditures / Fixed Assets', 'totaling_type' => AccountScheduleTotalingType::TOTAL_ACCOUNTS, 'totaling' => '15000..19999', 'bold' => false, 'indentation' => 1],

            ['line_no' => 30000, 'row_no' => 'H3', 'description' => 'FINANCING ACTIVITIES', 'totaling_type' => AccountScheduleTotalingType::COMMENT, 'totaling' => null, 'bold' => true, 'indentation' => 0],
            ['line_no' => 31000, 'row_no' => 'DEBT', 'description' => 'Debt / Non-Current Liabilities', 'totaling_type' => AccountScheduleTotalingType::TOTAL_ACCOUNTS, 'totaling' => '25000..29999', 'bold' => false, 'indentation' => 1],
            ['line_no' => 32000, 'row_no' => 'EQ', 'description' => 'Equity / Dividends', 'totaling_type' => AccountScheduleTotalingType::TOTAL_ACCOUNTS, 'totaling' => '30000..39999', 'bold' => false, 'indentation' => 1],
        ];

        foreach ($lines as $line) {
            $schedule->lines()->updateOrCreate(
                ['line_no' => $line['line_no']],
                [
                    'row_no' => $line['row_no'],
                    'description' => $line['description'],
                    'totaling_type' => $line['totaling_type'],
                    'totaling' => $line['totaling'],
                    'row_type' => AccountScheduleRowType::NET_CHANGE,
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
