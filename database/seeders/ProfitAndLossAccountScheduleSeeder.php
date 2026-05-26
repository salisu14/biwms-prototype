<?php

namespace Database\Seeders;

use App\Enums\AccountScheduleAmountType;
use App\Enums\AccountScheduleRowType;
use App\Enums\AccountScheduleTotalingType;
use App\Models\AccountSchedule;
use Illuminate\Database\Seeder;

class ProfitAndLossAccountScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schedule = AccountSchedule::query()->updateOrCreate(
            ['name' => 'Default Profit & Loss'],
            ['description' => 'BC-style default profit and loss layout']
        );

        $lines = [
            ['line_no' => 10000, 'row_no' => 'H1', 'description' => 'REVENUE', 'totaling_type' => AccountScheduleTotalingType::COMMENT, 'totaling' => null, 'bold' => true, 'indentation' => 0, 'show_opposite_sign' => false],
            ['line_no' => 11000, 'row_no' => 'R1', 'description' => 'Net Revenue', 'totaling_type' => AccountScheduleTotalingType::TOTAL_ACCOUNTS, 'totaling' => '40000..49999', 'bold' => true, 'indentation' => 1, 'show_opposite_sign' => true],

            ['line_no' => 20000, 'row_no' => 'H2', 'description' => 'COST OF GOODS SOLD', 'totaling_type' => AccountScheduleTotalingType::COMMENT, 'totaling' => null, 'bold' => true, 'indentation' => 0, 'show_opposite_sign' => false],
            ['line_no' => 21000, 'row_no' => 'C1', 'description' => 'Total COGS', 'totaling_type' => AccountScheduleTotalingType::TOTAL_ACCOUNTS, 'totaling' => '50000..59999', 'bold' => true, 'indentation' => 1, 'show_opposite_sign' => false],

            ['line_no' => 22000, 'row_no' => 'GP', 'description' => 'Gross Profit', 'totaling_type' => AccountScheduleTotalingType::FORMULA, 'totaling' => 'R1 - C1', 'bold' => true, 'indentation' => 0, 'show_opposite_sign' => false],

            ['line_no' => 30000, 'row_no' => 'H3', 'description' => 'OPERATING EXPENSES', 'totaling_type' => AccountScheduleTotalingType::COMMENT, 'totaling' => null, 'bold' => true, 'indentation' => 0, 'show_opposite_sign' => false],
            ['line_no' => 31000, 'row_no' => 'E1', 'description' => 'Operating Expenses', 'totaling_type' => AccountScheduleTotalingType::TOTAL_ACCOUNTS, 'totaling' => '60000..69999', 'bold' => true, 'indentation' => 1, 'show_opposite_sign' => false],

            ['line_no' => 32000, 'row_no' => 'OI', 'description' => 'Operating Income', 'totaling_type' => AccountScheduleTotalingType::FORMULA, 'totaling' => 'GP - E1', 'bold' => true, 'indentation' => 0, 'show_opposite_sign' => false],

            ['line_no' => 40000, 'row_no' => 'H4', 'description' => 'OTHER INCOME / EXPENSES', 'totaling_type' => AccountScheduleTotalingType::COMMENT, 'totaling' => null, 'bold' => true, 'indentation' => 0, 'show_opposite_sign' => false],
            ['line_no' => 41000, 'row_no' => 'OI2', 'description' => 'Other Income / Expense (Net)', 'totaling_type' => AccountScheduleTotalingType::TOTAL_ACCOUNTS, 'totaling' => '70000..89999', 'bold' => true, 'indentation' => 1, 'show_opposite_sign' => false],

            ['line_no' => 42000, 'row_no' => 'NI', 'description' => 'Net Income', 'totaling_type' => AccountScheduleTotalingType::FORMULA, 'totaling' => 'OI + OI2', 'bold' => true, 'indentation' => 0, 'show_opposite_sign' => false],
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
                    'show_opposite_sign' => $line['show_opposite_sign'],
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
