<?php

namespace Database\Seeders;

use App\Enums\AccountCategory;
use App\Enums\AccountType;
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
            // ============================================
            // Revenue Accounts (40000-40999)
            // ============================================
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

            // ============================================
            // COGS Accounts (50000-50999)
            // ============================================
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
            [
                'account_number' => '50300',
                'name' => 'Purchase Variance',
                'account_type' => AccountType::COGS,
                'account_category' => AccountCategory::COGS,
                'direct_posting' => true,
                'parent_account_number' => '50000',
            ],
            [
                'account_number' => '50400',
                'name' => 'Inventory Adjustment',
                'account_type' => AccountType::COGS,
                'account_category' => AccountCategory::COGS,
                'direct_posting' => true,
                'parent_account_number' => '50000',
            ],

            // ============================================
            // Expense Accounts (60000-69999)
            // ============================================
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

            // ============================================
            // INVENTORY ASSET ACCOUNTS (13000-13999 - Current Assets)
            // Aligned with GeneralPostingSetupSeeder expectations
            // ============================================

            // Parent/Control Accounts
            [
                'account_number' => '13000',
                'name' => 'Inventory',
                'account_type' => AccountType::ASSET,
                'account_category' => AccountCategory::CURRENT_ASSET,
                'direct_posting' => false,                  // Control account
            ],
            [
                'account_number' => '13100',
                'name' => 'Raw Materials',
                'account_type' => AccountType::ASSET,
                'account_category' => AccountCategory::CURRENT_ASSET,
                'direct_posting' => false,                  // Control account
            ],
            [
                'account_number' => '13200',
                'name' => 'Finished Goods',
                'account_type' => AccountType::ASSET,
                'account_category' => AccountCategory::CURRENT_ASSET,
                'direct_posting' => false,                  // Control account
            ],
            [
                'account_number' => '13300',
                'name' => 'Work in Process',
                'account_type' => AccountType::ASSET,
                'account_category' => AccountCategory::CURRENT_ASSET,
                'direct_posting' => false,                  // Control account
            ],

            // Specific Inventory Accounts (Direct Posting)
            [
                'account_number' => '13110',
                'name' => 'Raw Materials - Warehouse',
                'account_type' => AccountType::ASSET,
                'account_category' => AccountCategory::CURRENT_ASSET,
                'direct_posting' => true,
                'parent_account_number' => '13100',
            ],
            [
                'account_number' => '13120',
                'name' => 'Raw Materials - In Transit',
                'account_type' => AccountType::ASSET,
                'account_category' => AccountCategory::CURRENT_ASSET,
                'direct_posting' => true,
                'parent_account_number' => '13100',
            ],
            [
                'account_number' => '13210',
                'name' => 'Finished Goods - Warehouse A',
                'account_type' => AccountType::ASSET,
                'account_category' => AccountCategory::CURRENT_ASSET,
                'direct_posting' => true,
                'parent_account_number' => '13200',
            ],
            [
                'account_number' => '13220',
                'name' => 'Finished Goods - Warehouse B',
                'account_type' => AccountType::ASSET,
                'account_category' => AccountCategory::CURRENT_ASSET,
                'direct_posting' => true,
                'parent_account_number' => '13200',
            ],
            [
                'account_number' => '13310',
                'name' => 'WIP - Production Floor',
                'account_type' => AccountType::ASSET,
                'account_category' => AccountCategory::CURRENT_ASSET,
                'direct_posting' => true,
                'parent_account_number' => '13300',
            ],
            [
                'account_number' => '13320',
                'name' => 'WIP - Subcontracting',
                'account_type' => AccountType::ASSET,
                'account_category' => AccountCategory::CURRENT_ASSET,
                'direct_posting' => true,
                'parent_account_number' => '13300',
            ],

            // ============================================
            // CONTRA/ADJUSTMENT ACCOUNTS (52000-52999 - COGS)
            // These offset inventory accounts during production posting
            // ============================================

            // Parent Account
            [
                'account_number' => '52000',
                'name' => 'Inventory Adjustments',
                'account_type' => AccountType::COGS,
                'account_category' => AccountCategory::COGS,
                'direct_posting' => false,                  // Control account
            ],

            // Direct Cost Applied Account (Material Cost Transfer)
            [
                'account_number' => '52100',
                'name' => 'Direct Cost Applied - Raw Materials',
                'account_type' => AccountType::COGS,
                'account_category' => AccountCategory::COGS,
                'direct_posting' => true,
                'parent_account_number' => '52000',
            ],

            // Overhead Applied Account (Indirect Manufacturing Costs)
            [
                'account_number' => '52200',
                'name' => 'Overhead Applied - Manufacturing',
                'account_type' => AccountType::COGS,
                'account_category' => AccountCategory::COGS,
                'direct_posting' => true,
                'parent_account_number' => '52000',
            ],

            // ============================================
            // CAPACITY/APPLIED COSTS (62000-62999 - Expense)
            // Alternative location for applied costs
            // ============================================
            [
                'account_number' => '62100',
                'name' => 'Direct Cost Applied - Labor/Machine',
                'account_type' => AccountType::EXPENSE,
                'account_category' => AccountCategory::OPERATING_EXPENSE,
                'direct_posting' => true,
                'parent_account_number' => '60000',
            ],
            [
                'account_number' => '62200',
                'name' => 'Overhead Applied - Capacity',
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
                'account_number' => '52000',
                'name' => 'Inventory Adjustments',
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
            [
                'account_number' => '13000',
                'name' => 'Inventory',
                'account_type' => AccountType::ASSET,
                'account_category' => AccountCategory::CURRENT_ASSET,
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
