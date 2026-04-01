<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\AccountCategory;
use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountSeeder extends Seeder
{
    public function run(): void
    {
        // First, create parent/control accounts if they don't exist
        $this->createParentAccounts();

        // Create specific accounts
        $accounts = [
            // Revenue Accounts (40000-40999)
            [
                'account_number' => '40100',
                'name' => 'Sales - Domestic Retail',
                'account_type' => AccountType::REVENUE,
                'account_category' => AccountCategory::REVENUE,
                'direct_posting' => true,
                'parent_account_number' => '40000',
            ],
            [
                'account_number' => '40200',
                'name' => 'Sales - Export',
                'account_type' => AccountType::REVENUE,
                'account_category' => AccountCategory::REVENUE,
                'direct_posting' => true,
                'parent_account_number' => '40000',
            ],

            // COGS Accounts (50000-50999)
            [
                'account_number' => '50100',
                'name' => 'COGS - Domestic Retail',
                'account_type' => AccountType::COGS,
                'account_category' => AccountCategory::COGS,
                'direct_posting' => true,
                'parent_account_number' => '50000',
            ],
            [
                'account_number' => '50200',
                'name' => 'COGS - Export',
                'account_type' => AccountType::COGS,
                'account_category' => AccountCategory::COGS,
                'direct_posting' => true,
                'parent_account_number' => '50000',
            ],

            // Expense Accounts (60000-69999)
            [
                'account_number' => '60100',
                'name' => 'Warehouse Labor',
                'account_type' => AccountType::EXPENSE,
                'account_category' => AccountCategory::OPERATING_EXPENSE,
                'direct_posting' => true,
                'parent_account_number' => '60000',
            ],
            [
                'account_number' => '60200',
                'name' => 'Freight & Shipping',
                'account_type' => AccountType::EXPENSE,
                'account_category' => AccountCategory::OPERATING_EXPENSE,
                'direct_posting' => true,
                'parent_account_number' => '60000',
            ],
        ];

        foreach ($accounts as $accountData) {
            $parentAccountNumber = $accountData['parent_account_number'] ?? null;
            unset($accountData['parent_account_number']);

            // Find parent account if specified
            if ($parentAccountNumber) {
                $parentAccount = ChartOfAccount::where('account_number', $parentAccountNumber)->first();
                $accountData['parent_account_id'] = $parentAccount?->id;
            }

            ChartOfAccount::updateOrCreate(
                ['account_number' => $accountData['account_number']],
                $accountData
            );
        }
    }

    /**
     * Create parent/control accounts for each range
     */
    private function createParentAccounts(): void
    {
        $parentAccounts = [
            [
                'account_number' => '40000',
                'name' => 'Revenue - Sales',
                'account_type' => AccountType::REVENUE,
                'account_category' => AccountCategory::REVENUE,
                'direct_posting' => false, // Control account - no direct posting
            ],
            [
                'account_number' => '50000',
                'name' => 'Cost of Goods Sold',
                'account_type' => AccountType::COGS,
                'account_category' => AccountCategory::COGS,
                'direct_posting' => false, // Control account - no direct posting
            ],
            [
                'account_number' => '60000',
                'name' => 'Operating Expenses',
                'account_type' => AccountType::EXPENSE,
                'account_category' => AccountCategory::OPERATING_EXPENSE,
                'direct_posting' => false, // Control account - no direct posting
            ],
        ];

        foreach ($parentAccounts as $accountData) {
            ChartOfAccount::updateOrCreate(
                ['account_number' => $accountData['account_number']],
                $accountData
            );
        }
    }
}
