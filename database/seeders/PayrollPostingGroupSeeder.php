<?php

namespace Database\Seeders;

use App\Enums\AccountCategory;
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
        $maps = [
            'salaries' => ['number' => '70010', 'category' => AccountCategory::OPERATING_EXPENSE],
            'wages' => ['number' => '70020', 'category' => AccountCategory::OPERATING_EXPENSE],
            'social_security' => ['number' => '21010', 'category' => AccountCategory::LIABILITY],
            'tax_payable' => ['number' => '21020', 'category' => AccountCategory::LIABILITY],
            'net_pay' => ['number' => '21030', 'category' => AccountCategory::LIABILITY],
        ];

        $accounts = [];

        foreach ($maps as $key => $config) {
            $number = $config['number'];
            $account = ChartOfAccount::where('account_number', $number)->first();

            if (! $account) {
                $account = ChartOfAccount::query()
                    ->where('account_category', $config['category'])
                    ->orderBy('id')
                    ->first();
            }

            if (! $account) {
                throw new Exception("Unable to resolve account for {$key} (expected number '{$number}' or fallback category '{$config['category']->value}').");
            }

            $accounts[$key] = $account->id;
        }

        return $accounts;
    }
}
