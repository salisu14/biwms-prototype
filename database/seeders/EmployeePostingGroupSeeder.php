<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\EmployeePostingGroup;
use Illuminate\Database\Seeder;

class EmployeePostingGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find or create a 'Salaries Payable' account
        $payablesAccount = ChartOfAccount::firstOrCreate(
            ['account_number' => '20200'],
            [
                'name' => 'Salaries Payable',
                'account_category' => 'PAYABLE',
                'account_type' => 'LIABILITY',
            ]
        );

        EmployeePostingGroup::updateOrCreate(
            ['code' => 'EMP'],
            [
                'description' => 'Standard Employee Salary Group',
                'payables_account_id' => $payablesAccount->id,
            ]
        );
    }
}
