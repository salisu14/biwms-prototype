<?php

namespace Database\Seeders;

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\CustomerPostingGroup;
use Illuminate\Database\Seeder;

class CustomerPostingGroupSeeder extends Seeder
{
    public function run(): void
    {
        // Create required Chart of Accounts first
        $this->createRequiredAccounts();

        $postingGroups = [
            [
                'code' => 'DOMESTIC',
                'description' => 'Domestic Customers',
                'receivables_account_id' => ChartOfAccount::where('account_number', '11100')->first()?->id,
                'payment_disc_debit_account_id' => ChartOfAccount::where('account_number', '40900')->first()?->id,
                'payment_disc_credit_account_id' => ChartOfAccount::where('account_number', '50900')->first()?->id,
                'invoice_rounding_account_id' => ChartOfAccount::where('account_number', '60950')->first()?->id,
                'debit_rounding_account_id' => ChartOfAccount::where('account_number', '60950')->first()?->id,
                'credit_rounding_account_id' => ChartOfAccount::where('account_number', '60950')->first()?->id,
            ],
            [
                'code' => 'FOREIGN',
                'description' => 'Foreign Customers',
                'receivables_account_id' => ChartOfAccount::where('account_number', '11200')->first()?->id,
                'payment_disc_debit_account_id' => ChartOfAccount::where('account_number', '40900')->first()?->id,
                'payment_disc_credit_account_id' => ChartOfAccount::where('account_number', '50900')->first()?->id,
                'invoice_rounding_account_id' => ChartOfAccount::where('account_number', '60950')->first()?->id,
                'debit_rounding_account_id' => ChartOfAccount::where('account_number', '60950')->first()?->id,
                'credit_rounding_account_id' => ChartOfAccount::where('account_number', '60950')->first()?->id,
            ],
            [
                'code' => 'EXPORT',
                'description' => 'Export Customers',
                'receivables_account_id' => ChartOfAccount::where('account_number', '11300')->first()?->id,
                'payment_disc_debit_account_id' => ChartOfAccount::where('account_number', '40900')->first()?->id,
                'payment_disc_credit_account_id' => ChartOfAccount::where('account_number', '50900')->first()?->id,
                'invoice_rounding_account_id' => ChartOfAccount::where('account_number', '60950')->first()?->id,
                'debit_rounding_account_id' => ChartOfAccount::where('account_number', '60950')->first()?->id,
                'credit_rounding_account_id' => ChartOfAccount::where('account_number', '60950')->first()?->id,
            ],
            [
                'code' => 'INTERCOMPANY',
                'description' => 'Intercompany Customers',
                'receivables_account_id' => ChartOfAccount::where('account_number', '11500')->first()?->id,
                'payment_disc_debit_account_id' => ChartOfAccount::where('account_number', '40900')->first()?->id,
                'payment_disc_credit_account_id' => ChartOfAccount::where('account_number', '50900')->first()?->id,
                'invoice_rounding_account_id' => ChartOfAccount::where('account_number', '60950')->first()?->id,
                'debit_rounding_account_id' => ChartOfAccount::where('account_number', '60950')->first()?->id,
                'credit_rounding_account_id' => ChartOfAccount::where('account_number', '60950')->first()?->id,
            ],
            [
                'code' => 'EMPLOYEE',
                'description' => 'Employee Customers',
                'receivables_account_id' => ChartOfAccount::where('account_number', '11800')->first()?->id,
                'payment_disc_debit_account_id' => ChartOfAccount::where('account_number', '40900')->first()?->id,
                'payment_disc_credit_account_id' => ChartOfAccount::where('account_number', '50900')->first()?->id,
                'invoice_rounding_account_id' => ChartOfAccount::where('account_number', '60950')->first()?->id,
                'debit_rounding_account_id' => ChartOfAccount::where('account_number', '60950')->first()?->id,
                'credit_rounding_account_id' => ChartOfAccount::where('account_number', '60950')->first()?->id,
            ],
        ];

        foreach ($postingGroups as $group) {
            if ($group['receivables_account_id']) {
                CustomerPostingGroup::updateOrCreate(
                    ['code' => $group['code']],
                    $group
                );
            }
        }

        $this->command->info('Customer Posting Groups seeded successfully!');
    }

    private function createRequiredAccounts(): void
    {
        $accounts = [
            // Receivables Accounts
            [
                'account_number' => '11100',
                'name' => 'Trade Receivables - Domestic',
                'account_type' => AccountType::ASSET,
                'account_category' => AccountCategory::RECEIVABLE
            ],
            [
                'account_number' => '11200',
                'name' => 'Trade Receivables - Foreign',
                'account_type' => AccountType::ASSET,
                'account_category' => AccountCategory::RECEIVABLE
            ],
            [
                'account_number' => '11300',
                'name' => 'Trade Receivables - Export',
                'account_type' => AccountType::ASSET,
                'account_category' => AccountCategory::RECEIVABLE
            ],
            [
                'account_number' => '11500',
                'name' => 'Intercompany Receivables',
                'account_type' => AccountType::ASSET,
                'account_category' => AccountCategory::RECEIVABLE
            ],
            [
                'account_number' => '11800',
                'name' => 'Employee Receivables',
                'account_type' => AccountType::ASSET,
                'account_category' => AccountCategory::RECEIVABLE
            ],
            // Discount and Rounding Accounts
            [
                'account_number' => '40900',
                'name' => 'Sales Discounts',
                'account_type' => AccountType::EXPENSE,
                'account_category' => AccountCategory::REVENUE
            ],
            [
                'account_number' => '50900',
                'name' => 'Purchase Discounts',
                'account_type' => AccountType::EXPENSE,
                'account_category' => AccountCategory::COGS
            ],
            [
                'account_number' => '60950',
                'name' => 'Invoice Rounding',
                'account_type' => AccountType::EXPENSE,
                'account_category' => AccountCategory::OPERATING_EXPENSE,
            ],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::firstOrCreate(
                ['account_number' => $account['account_number']],
                $account
            );
        }
    }
}
