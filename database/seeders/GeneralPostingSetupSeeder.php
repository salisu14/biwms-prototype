<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use Exception;
use Illuminate\Database\Seeder;

class GeneralPostingSetupSeeder extends Seeder
{
    public function run(): void
    {
        // Get posting groups by code
        $busGroups = $this->getBusinessPostingGroups();
        $prodGroups = $this->getProductPostingGroups();

        // Get account IDs
        $accounts = $this->getAccounts();

        $setups = [
            // ============================================
            // SALES POSTING
            // ============================================
            [
                'general_business_posting_group_id' => $busGroups['DOMESTIC'],
                'general_product_posting_group_id' => $prodGroups['FINISHED'],
                'sales_account_id' => $accounts['sales_domestic'],
                'sales_credit_memo_account_id' => $accounts['sales_domestic'],
                'cogs_account_id' => $accounts['cogs_domestic'],
                'cogs_credit_memo_account_id' => $accounts['cogs_domestic'],
            ],

            [
                'general_business_posting_group_id' => $busGroups['EXPORT'],
                'general_product_posting_group_id' => $prodGroups['FINISHED'],
                'sales_account_id' => $accounts['sales_export'],
                'sales_credit_memo_account_id' => $accounts['sales_export'],
                'cogs_account_id' => $accounts['cogs_export'],
                'cogs_credit_memo_account_id' => $accounts['cogs_export'],
            ],

            // ============================================
            // PURCHASE POSTING (Raw Materials)
            // ============================================
            [
                'general_business_posting_group_id' => $busGroups['DOMESTIC'],
                'general_product_posting_group_id' => $prodGroups['RAWMAT'],
                'inventory_account_id' => $accounts['inventory_rawmat'],
                'direct_cost_applied_account_id' => $accounts['direct_cost_applied_mat'],
                'purchase_variance_account_id' => $accounts['purchase_variance'],
            ],

            [
                'general_business_posting_group_id' => $busGroups['FOREIGN'],
                'general_product_posting_group_id' => $prodGroups['RAWMAT'],
                'inventory_account_id' => $accounts['inventory_rawmat'],
                'direct_cost_applied_account_id' => $accounts['direct_cost_applied_mat'],
                'purchase_variance_account_id' => $accounts['purchase_variance'],
            ],

            // ============================================
            // PURCHASE POSTING (Expenses / Assets)
            // ============================================
            // FIX: This is likely the combination you need for EXPT-002
            // It uses the 'EXPENSE' product group (ID 9) + DOMESTIC
            [
                'general_business_posting_group_id' => $busGroups['DOMESTIC'],
                'general_product_posting_group_id' => $prodGroups['EXPENSE'], // Assuming ID 9 is EXPENSE
                'purchase_account_id' => $accounts['expense_account'], // Use a specific expense account
                // Sales/COGS are irrelevant for pure expense purchases
            ],

            // ============================================
            // PRODUCTION POSTING
            // ============================================
            [
                'general_business_posting_group_id' => $busGroups['MANUFACTURING'],
                'general_product_posting_group_id' => $prodGroups['FINISHED'],
                'inventory_account_id' => $accounts['inventory_finished'],
                'inventory_adj_account_id' => $accounts['wip_account'],
            ],

            [
                'general_business_posting_group_id' => $busGroups['MANUFACTURING'],
                'general_product_posting_group_id' => $prodGroups['RAWMAT'],
                'inventory_account_id' => $accounts['inventory_rawmat'],
                'inventory_adj_account_id' => $accounts['wip_account'],
            ],

            [
                'general_business_posting_group_id' => $busGroups['MANUFACTURING'],
                'general_product_posting_group_id' => $prodGroups['WIP'],
                'inventory_account_id' => $accounts['wip_account'],
            ],

            [
                'general_business_posting_group_id' => $busGroups['MANUFACTURING'],
                'general_product_posting_group_id' => $prodGroups['CAPACITY'],
                'inventory_adj_account_id' => $accounts['wip_account'],
            ],
        ];

        foreach ($setups as $setup) {
            GeneralPostingSetup::updateOrCreate(
                [
                    'general_business_posting_group_id' => $setup['general_business_posting_group_id'],
                    'general_product_posting_group_id' => $setup['general_product_posting_group_id'],
                ],
                $setup
            );
        }
    }

    /**
     * @throws Exception
     */
    private function getBusinessPostingGroups(): array
    {
        $codes = ['DOMESTIC', 'EXPORT', 'FOREIGN', 'MANUFACTURING'];
        $groups = [];

        foreach ($codes as $code) {
            $group = GeneralBusinessPostingGroup::where('code', $code)->first();
            if (! $group) {
                $group = GeneralBusinessPostingGroup::firstOrCreate(
                    ['code' => $code],
                    [
                        'description' => $code . ' Auto-created (missing seed)',
                        'default_vat_business_posting_group_id' => null,
                        'auto_create_vat_bus_posting_group' => false,
                        'blocked' => false,
                    ]
                );
            }
            $groups[$code] = $group->id;
        }

        return $groups;
    }

    /**
     * @throws Exception
     */
    private function getProductPostingGroups(): array
    {
        // ADDED 'EXPENSE' to the list of codes to fetch
        // If ID 9 is actually 'DTA', 'ASSET', or 'SERVICES', add it here instead.
        $codes = ['RAWMAT', 'WIP', 'FINISHED', 'CAPACITY', 'EXPENSE'];
        $groups = [];

        foreach ($codes as $code) {
            $group = GeneralProductPostingGroup::where('code', $code)->first();
            if (! $group) {
                throw new Exception("GeneralProductPostingGroup '{$code}' not found.");
            }
            $groups[$code] = $group->id;
        }

        return $groups;
    }

    /**
     * @throws Exception
     */
    private function getAccounts(): array
    {
        $accountMap = [
            'sales_domestic' => '40100',
            'sales_export' => '40200',
            'cogs_domestic' => '50100',
            'cogs_export' => '50200',
            'inventory_finished' => '13200',
            'inventory_rawmat' => '13100',
            'wip_account' => '13300',
            'direct_cost_applied_mat' => '52100',
            'direct_cost_applied_cap' => '62100',
            'overhead_applied' => '62200',
            'purchase_variance' => '50300',
            'inventory_adjustment' => '50400',
            // ADDED: Generic Expense Account for the EXPENSE setup
            'expense_account' => '60100',
        ];

        $accounts = [];

        foreach ($accountMap as $key => $accountNumber) {
            $account = ChartOfAccount::where('account_number', $accountNumber)->first();
            if (! $account) {
                throw new Exception("ChartOfAccount '{$accountNumber}' ({$key}) not found.");
            }
            $accounts[$key] = $account->id;
        }

        return $accounts;
    }
}
