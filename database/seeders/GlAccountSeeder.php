<?php

namespace Database\Seeders;

use App\Enums\GlAccountType;
use App\Enums\GlAccountCategory;
use App\Enums\IncomeBalanceType;
use App\Enums\DebitCreditType;
use App\Models\GlAccount;
use Illuminate\Database\Seeder;

class GlAccountSeeder extends Seeder
{
    public function run(): void
    {
        // Create parent/control accounts first (Headers/Totals)
        $this->createParentAccounts();

        // Create detailed accounts (Posting)
        $this->createAssetAccounts();
        $this->createLiabilityAccounts();
        $this->createEquityAccounts();
        $this->createRevenueAccounts();
        $this->createCogsAccounts();
        $this->createExpenseAccounts();
    }

    /**
     * Create parent/control accounts (headers)
     */
    private function createParentAccounts(): void
    {
        $parents = [
            // Assets (10000-19999)
            ['account_no' => '10000', 'account_name' => 'ASSETS', 'account_type' => GlAccountType::HEADING, 'account_category' => GlAccountCategory::ASSETS, 'direct_posting' => false, 'income_balance' => IncomeBalanceType::BALANCE_SHEET],
            ['account_no' => '11000', 'account_name' => 'Current Assets', 'account_type' => GlAccountType::HEADING, 'account_category' => GlAccountCategory::ASSETS, 'direct_posting' => false, 'parent_account_no' => '10000', 'income_balance' => IncomeBalanceType::BALANCE_SHEET],
            ['account_no' => '12000', 'account_name' => 'Fixed Assets', 'account_type' => GlAccountType::HEADING, 'account_category' => GlAccountCategory::ASSETS, 'direct_posting' => false, 'parent_account_no' => '10000', 'income_balance' => IncomeBalanceType::BALANCE_SHEET],

            // Liabilities (20000-29999)
            ['account_no' => '20000', 'account_name' => 'LIABILITIES', 'account_type' => GlAccountType::HEADING, 'account_category' => GlAccountCategory::LIABILITIES, 'direct_posting' => false, 'income_balance' => IncomeBalanceType::BALANCE_SHEET],
            ['account_no' => '21000', 'account_name' => 'Current Liabilities', 'account_type' => GlAccountType::HEADING, 'account_category' => GlAccountCategory::LIABILITIES, 'direct_posting' => false, 'parent_account_no' => '20000', 'income_balance' => IncomeBalanceType::BALANCE_SHEET],

            // Equity (30000-39999)
            ['account_no' => '30000', 'account_name' => 'EQUITY', 'account_type' => GlAccountType::HEADING, 'account_category' => GlAccountCategory::EQUITY, 'direct_posting' => false, 'income_balance' => IncomeBalanceType::BALANCE_SHEET],

            // Revenue (40000-49999)
            ['account_no' => '40000', 'account_name' => 'REVENUE', 'account_type' => GlAccountType::HEADING, 'account_category' => GlAccountCategory::INCOME, 'direct_posting' => false, 'income_balance' => IncomeBalanceType::INCOME_STATEMENT],

            // COGS (50000-59999)
            ['account_no' => '50000', 'account_name' => 'COST OF GOODS SOLD', 'account_type' => GlAccountType::HEADING, 'account_category' => GlAccountCategory::COGS, 'direct_posting' => false, 'income_balance' => IncomeBalanceType::INCOME_STATEMENT],

            // Expenses (60000-69999)
            ['account_no' => '60000', 'account_name' => 'OPERATING EXPENSES', 'account_type' => GlAccountType::HEADING, 'account_category' => GlAccountCategory::EXPENSE, 'direct_posting' => false, 'income_balance' => IncomeBalanceType::INCOME_STATEMENT],
        ];

        foreach ($parents as $account) {
            $this->createAccount($account);
        }
    }

    /**
     * Asset accounts
     */
    private function createAssetAccounts(): void
    {
        $accounts = [
            ['account_no' => '11100', 'account_name' => 'Cash on Hand', 'account_type' => GlAccountType::POSTING, 'account_category' => GlAccountCategory::ASSETS, 'parent_account_no' => '11000', 'income_balance' => IncomeBalanceType::BALANCE_SHEET],
            ['account_no' => '11200', 'account_name' => 'Accounts Receivable', 'account_type' => GlAccountType::POSTING, 'account_category' => GlAccountCategory::ASSETS, 'parent_account_no' => '11000', 'reconciliation_account' => true, 'income_balance' => IncomeBalanceType::BALANCE_SHEET],
            ['account_no' => '11300', 'account_name' => 'Inventory', 'account_type' => GlAccountType::POSTING, 'account_category' => GlAccountCategory::ASSETS, 'parent_account_no' => '11000', 'income_balance' => IncomeBalanceType::BALANCE_SHEET],
        ];

        foreach ($accounts as $account) {
            $this->createAccount($account);
        }
    }

    /**
     * Liability accounts
     */
    private function createLiabilityAccounts(): void
    {
        $accounts = [
            ['account_no' => '21100', 'account_name' => 'Accounts Payable', 'account_type' => GlAccountType::POSTING, 'account_category' => GlAccountCategory::LIABILITIES, 'parent_account_no' => '21000', 'reconciliation_account' => true, 'income_balance' => IncomeBalanceType::BALANCE_SHEET],
            ['account_no' => '21420', 'account_name' => 'VAT Payable', 'account_type' => GlAccountType::POSTING, 'account_category' => GlAccountCategory::LIABILITIES, 'parent_account_no' => '21000', 'income_balance' => IncomeBalanceType::BALANCE_SHEET],
        ];

        foreach ($accounts as $account) {
            $this->createAccount($account);
        }
    }

    /**
     * Equity accounts
     */
    private function createEquityAccounts(): void
    {
        $accounts = [
            ['account_no' => '31100', 'account_name' => 'Common Stock', 'account_type' => GlAccountType::POSTING, 'account_category' => GlAccountCategory::EQUITY, 'parent_account_no' => '30000', 'income_balance' => IncomeBalanceType::BALANCE_SHEET],
            ['account_no' => '32100', 'account_name' => 'Retained Earnings', 'account_type' => GlAccountType::POSTING, 'account_category' => GlAccountCategory::EQUITY, 'parent_account_no' => '30000', 'income_balance' => IncomeBalanceType::BALANCE_SHEET],
        ];

        foreach ($accounts as $account) {
            $this->createAccount($account);
        }
    }

    /**
     * Revenue accounts
     */
    private function createRevenueAccounts(): void
    {
        $accounts = [
            ['account_no' => '41100', 'account_name' => 'Sales Revenue - Domestic', 'account_type' => GlAccountType::POSTING, 'account_category' => GlAccountCategory::INCOME, 'parent_account_no' => '40000', 'income_balance' => IncomeBalanceType::INCOME_STATEMENT, 'debit_credit' => DebitCreditType::CREDIT],
        ];

        foreach ($accounts as $account) {
            $this->createAccount($account);
        }
    }

    /**
     * COGS accounts
     */
    private function createCogsAccounts(): void
    {
        $accounts = [
            ['account_no' => '51100', 'account_name' => 'Cost of Goods Sold', 'account_type' => GlAccountType::POSTING, 'account_category' => GlAccountCategory::COGS, 'parent_account_no' => '50000', 'income_balance' => IncomeBalanceType::INCOME_STATEMENT, 'debit_credit' => DebitCreditType::DEBIT],
        ];

        foreach ($accounts as $account) {
            $this->createAccount($account);
        }
    }

    /**
     * Expense accounts
     */
    private function createExpenseAccounts(): void
    {
        $accounts = [
            ['account_no' => '61100', 'account_name' => 'Salaries & Wages', 'account_type' => GlAccountType::POSTING, 'account_category' => GlAccountCategory::EXPENSE, 'parent_account_no' => '60000', 'income_balance' => IncomeBalanceType::INCOME_STATEMENT, 'debit_credit' => DebitCreditType::DEBIT],
            ['account_no' => '62100', 'account_name' => 'Rent Expense', 'account_type' => GlAccountType::POSTING, 'account_category' => GlAccountCategory::EXPENSE, 'parent_account_no' => '60000', 'income_balance' => IncomeBalanceType::INCOME_STATEMENT, 'debit_credit' => DebitCreditType::DEBIT],
        ];

        foreach ($accounts as $account) {
            $this->createAccount($account);
        }
    }

    /**
     * Helper to create the account and link parent by account_no
     */
    private function createAccount(array $data): void
    {
        $parentNo = $data['parent_account_no'] ?? null;
        unset($data['parent_account_no']);

        if ($parentNo) {
            $parent = GlAccount::where('account_no', $parentNo)->first();
            $data['parent_account_id'] = $parent?->id;
        }

        GlAccount::create($data);
    }
}
