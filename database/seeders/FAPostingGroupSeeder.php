<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\FAPostingGroup;
use Illuminate\Database\Seeder;

class FAPostingGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $assetAccount = ChartOfAccount::where('account_number', '13000')->first()?->id;
        $bankAccount = ChartOfAccount::where('account_number', '10100')->first()?->id;
        $depExpenseAccount = ChartOfAccount::where('account_number', '60000')->first()?->id;
        $revenueAccount = ChartOfAccount::where('account_number', '40000')->first()?->id;

        $groups = [
            [
                'code' => 'MACHINERY',
                'description' => 'Plant & Machinery',
                'acquisition_cost_account_id' => $assetAccount,
                'depreciation_account_id' => $assetAccount,
                'depreciation_expense_account_id' => $depExpenseAccount,
                'disposal_proceeds_account_id' => $revenueAccount,
            ],
            [
                'code' => 'VEHICLES',
                'description' => 'Motor Vehicles',
                'acquisition_cost_account_id' => $assetAccount,
                'depreciation_account_id' => $assetAccount,
                'depreciation_expense_account_id' => $depExpenseAccount,
                'disposal_proceeds_account_id' => $revenueAccount,
            ],
            [
                'code' => 'BUILDINGS',
                'description' => 'Factory & Office Buildings',
                'acquisition_cost_account_id' => $assetAccount,
                'depreciation_account_id' => $assetAccount,
                'depreciation_expense_account_id' => $depExpenseAccount,
                'disposal_proceeds_account_id' => $revenueAccount,
            ],
            [
                'code' => 'INTANGIBLE',
                'description' => 'Licenses & Patents',
                'acquisition_cost_account_id' => $assetAccount,
                'depreciation_account_id' => $assetAccount,
                'depreciation_expense_account_id' => $depExpenseAccount,
                'disposal_proceeds_account_id' => $revenueAccount,
            ],
            [
                'code' => 'LIQUIDITY',
                'description' => 'Cash & Bank Balances',
                'acquisition_cost_account_id' => $bankAccount, // For liquidity, "Acquisition" is the primary balance account
            ],
        ];

        foreach ($groups as $group) {
            FAPostingGroup::updateOrCreate(['code' => $group['code']], $group);
        }
    }
}
