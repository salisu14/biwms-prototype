<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\PayrollPostingGroup;
use Exception;
use Illuminate\Database\Seeder;

class PayrollPostingGroupSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = $this->getAccounts();

        PayrollPostingGroup::updateOrCreate(
            ['code' => 'DEFAULT'],
            [
                'description' => 'Default Payroll Posting Group',

                'salaries_account_id' => $accounts['salaries'],
                'wages_account_id' => $accounts['wages'],
                'social_security_account_id' => $accounts['social_security'],
                'tax_payable_account_id' => $accounts['tax_payable'],
                'net_pay_account_id' => $accounts['net_pay'],
            ]
        );
    }

    /**
     * @throws Exception
     */
    private function getAccounts(): array
    {
        $map = [
            'salaries' => '70010',
            'wages' => '70020',
            'social_security' => '21010',
            'tax_payable' => '21020',
            'net_pay' => '21030',
        ];

        $accounts = [];

        foreach ($map as $key => $number) {
            $account = ChartOfAccount::where('account_number', $number)->first();

            if (!$account) {
                throw new Exception("ChartOfAccount '{$number}' missing for {$key}");
            }

            $accounts[$key] = $account->id;
        }

        return $accounts;
    }
}
