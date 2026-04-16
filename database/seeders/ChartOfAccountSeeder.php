<?php

namespace Database\Seeders;

use App\Enums\AccountCategory;
use App\Enums\AccountStructuralType;
use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountSeeder extends Seeder
{
    public function run(): void
    {
        // First, create parent/control accounts
        $this->createParentAccounts();

        // Create specific accounts
        $accounts = [
            // ============================================
            // Revenue Accounts
            // ============================================
            [
                'account_number' => '40100',
                'name' => 'Sales - Domestic Retail',
                'structural_type' => AccountStructuralType::POSTING,
                'account_category' => AccountCategory::REVENUE,
                'direct_posting' => true,
                'parent_account_number' => '40000',
            ],
            [
                'account_number' => '40200',
                'name' => 'Sales - Export',
                'structural_type' => AccountStructuralType::POSTING,
                'account_category' => AccountCategory::REVENUE,
                'direct_posting' => true,
                'parent_account_number' => '40000',
            ],

            // ============================================
            // COGS Accounts
            // ============================================
            [
                'account_number' => '50100',
                'name' => 'COGS - Domestic Retail',
                'structural_type' => AccountStructuralType::POSTING,
                'account_category' => AccountCategory::COGS,
                'direct_posting' => true,
                'parent_account_number' => '50000',
            ],
            [
                'account_number' => '50200',
                'name' => 'COGS - Export',
                'structural_type' => AccountStructuralType::POSTING,
                'account_category' => AccountCategory::COGS,
                'direct_posting' => true,
                'parent_account_number' => '50000',
            ],
            [
                'account_number' => '50300',
                'name' => 'Purchase Variance',
                'structural_type' => AccountStructuralType::POSTING,
                'account_category' => AccountCategory::COGS,
                'direct_posting' => true,
                'parent_account_number' => '50000',
            ],
            [
                'account_number' => '50400',
                'name' => 'Inventory Adjustment',
                'structural_type' => AccountStructuralType::POSTING,
                'account_category' => AccountCategory::COGS,
                'direct_posting' => true,
                'parent_account_number' => '50000',
            ],

            // ============================================
            // Expense Accounts
            // ============================================
            [
                'account_number' => '60100',
                'name' => 'Warehouse Labor',
                'structural_type' => AccountStructuralType::POSTING,
                'account_category' => AccountCategory::OPERATING_EXPENSE,
                'direct_posting' => true,
                'parent_account_number' => '60000',
            ],
            [
                'account_number' => '60200',
                'name' => 'Freight & Shipping',
                'structural_type' => AccountStructuralType::POSTING,
                'account_category' => AccountCategory::OPERATING_EXPENSE,
                'direct_posting' => true,
                'parent_account_number' => '60000',
            ],

            // ============================================
            // Inventory Asset Accounts
            // ============================================
            [
                'account_number' => '13100',
                'name' => 'Raw Materials',
                'structural_type' => AccountStructuralType::HEADING,
                'account_category' => AccountCategory::INVENTORY,
                'direct_posting' => false,
                'parent_account_number' => '13000',
            ],
            [
                'account_number' => '13110',
                'name' => 'Raw Materials - Warehouse',
                'structural_type' => AccountStructuralType::POSTING,
                'account_category' => AccountCategory::INVENTORY,
                'direct_posting' => true,
                'parent_account_number' => '13100',
            ],
            [
                'account_number' => '13200',
                'name' => 'Finished Goods',
                'structural_type' => AccountStructuralType::HEADING,
                'account_category' => AccountCategory::INVENTORY,
                'direct_posting' => false,
                'parent_account_number' => '13000',
            ],
            [
                'account_number' => '13210',
                'name' => 'Finished Goods - Warehouse A',
                'structural_type' => AccountStructuralType::POSTING,
                'account_category' => AccountCategory::INVENTORY,
                'direct_posting' => true,
                'parent_account_number' => '13200',
            ],
            [
                'account_number' => '13300',
                'name' => 'Work in Process',
                'structural_type' => AccountStructuralType::HEADING,
                'account_category' => AccountCategory::INVENTORY,
                'direct_posting' => false,
                'parent_account_number' => '13000',
            ],

            // ============================================
            // Tax & Liabilities
            // ============================================
            [
                'account_number' => '14100',
                'name' => 'Purchase VAT (Input)',
                'structural_type' => AccountStructuralType::POSTING,
                'account_category' => AccountCategory::ASSET,
                'direct_posting' => true,
                'parent_account_number' => '14000',
            ],
            [
                'account_number' => '20100',
                'name' => 'Sales VAT (Output)',
                'structural_type' => AccountStructuralType::POSTING,
                'account_category' => AccountCategory::LIABILITY,
                'direct_posting' => true,
                'parent_account_number' => '20000',
            ],
        ];

        foreach ($accounts as $accountData) {
            $parentAccountNumber = $accountData['parent_account_number'] ?? null;
            unset($accountData['parent_account_number']);

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

    private function createParentAccounts(): void
    {
        $parentAccounts = [
            [
                'account_number' => '40000',
                'name' => 'Revenue - Sales',
                'structural_type' => AccountStructuralType::HEADING,
                'account_category' => AccountCategory::REVENUE,
                'direct_posting' => false,
            ],
            [
                'account_number' => '50000',
                'name' => 'Cost of Goods Sold',
                'structural_type' => AccountStructuralType::HEADING,
                'account_category' => AccountCategory::COGS,
                'direct_posting' => false,
            ],
            [
                'account_number' => '52100',
                'name' => 'Direct Cost of Goods Sold',
                'structural_type' => AccountStructuralType::HEADING,
                'account_category' => AccountCategory::COGS,
                'direct_posting' => false,
            ],
            [
                'account_number' => '60000',
                'name' => 'Operating Expenses',
                'structural_type' => AccountStructuralType::HEADING,
                'account_category' => AccountCategory::OPERATING_EXPENSE,
                'direct_posting' => false,
            ],
            [
                'account_number' => '62100',
                'name' => 'Direct Cost Applied Cap',
                'structural_type' => AccountStructuralType::HEADING,
                'account_category' => AccountCategory::OPERATING_EXPENSE,
                'direct_posting' => false,
            ],
            [
                'account_number' => '13000',
                'name' => 'Inventory',
                'structural_type' => AccountStructuralType::HEADING,
                'account_category' => AccountCategory::INVENTORY,
                'direct_posting' => false,
            ],
            [
                'account_number' => '14000',
                'name' => 'Other Current Assets',
                'structural_type' => AccountStructuralType::HEADING,
                'account_category' => AccountCategory::ASSET,
                'direct_posting' => false,
            ],
            [
                'account_number' => '20000',
                'name' => 'Current Liabilities',
                'structural_type' => AccountStructuralType::HEADING,
                'account_category' => AccountCategory::LIABILITY,
                'direct_posting' => false,
            ],
            [
                'account_number' => '62200',
                'name' => 'Overhead Applied',
                'structural_type' => AccountStructuralType::HEADING,
                'account_category' => AccountCategory::LIABILITY,
                'direct_posting' => false,
            ],
            [
                'account_number' => '50300',
                'name' => 'Purchase Variance',
                'structural_type' => AccountStructuralType::HEADING,
                'account_category' => AccountCategory::LIABILITY,
                'direct_posting' => false,
            ],
            [
                'account_number' => '50400',
                'name' => 'Inventory Adjustment',
                'structural_type' => AccountStructuralType::HEADING,
                'account_category' => AccountCategory::LIABILITY,
                'direct_posting' => false,
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
