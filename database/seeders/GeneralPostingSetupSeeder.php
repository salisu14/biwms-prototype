<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use Illuminate\Database\Seeder;

class GeneralPostingSetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get posting groups by code
        $busGroups = $this->getBusinessPostingGroups();
        $prodGroups = $this->getProductPostingGroups();

        // Get account IDs
        $accounts = $this->getAccounts();

        $setups = [
            // ============================================
            // SALES POSTING (Customer Group + Product Group)
            // ============================================

            // Domestic Sales of Finished Goods
            [
                'general_business_posting_group_id' => $busGroups['DOMESTIC'],
                'general_product_posting_group_id' => $prodGroups['FINISHED'],
                'sales_account_id' => $accounts['sales_domestic'],
                'sales_credit_memo_account_id' => $accounts['sales_domestic'],
                'cogs_account_id' => $accounts['cogs_domestic'],
                'cogs_credit_memo_account_id' => $accounts['cogs_domestic'],
                'inventory_account_id' => $accounts['inventory_finished'],      // Inventory asset account
                'inventory_adj_account_id' => $accounts['inventory_adjustment'], // For adjustments
            ],

            // Export Sales of Finished Goods
            [
                'general_business_posting_group_id' => $busGroups['EXPORT'],
                'general_product_posting_group_id' => $prodGroups['FINISHED'],
                'sales_account_id' => $accounts['sales_export'],
                'sales_credit_memo_account_id' => $accounts['sales_export'],
                'cogs_account_id' => $accounts['cogs_export'],
                'cogs_credit_memo_account_id' => $accounts['cogs_export'],
                'inventory_account_id' => $accounts['inventory_finished'],
                'inventory_adj_account_id' => $accounts['inventory_adjustment'],
            ],

            // ============================================
            // PURCHASE POSTING (Vendor Group + Product Group)
            // ============================================

            // Purchase of Raw Materials - Domestic
            [
                'general_business_posting_group_id' => $busGroups['DOMESTIC'],
                'general_product_posting_group_id' => $prodGroups['RAWMAT'],
                // Purchase accounts
                'inventory_account_id' => $accounts['inventory_rawmat'],        // Raw material inventory
                'direct_cost_applied_account_id' => $accounts['direct_cost_applied_mat'], // Applied to production
                'purchase_variance_account_id' => $accounts['purchase_variance'], // Price variances
                // Note: No sales/COGS accounts for purchase-only setups
            ],

            // Purchase of Raw Materials - Import
            [
                'general_business_posting_group_id' => $busGroups['FOREIGN'],
                'general_product_posting_group_id' => $prodGroups['RAWMAT'],
                'inventory_account_id' => $accounts['inventory_rawmat'],
                'direct_cost_applied_account_id' => $accounts['direct_cost_applied_mat'],
                'purchase_variance_account_id' => $accounts['purchase_variance'],
            ],

            // Purchase of Finished Goods (for resale)
            [
                'general_business_posting_group_id' => $busGroups['DOMESTIC'],
                'general_product_posting_group_id' => $prodGroups['FINISHED'],
                'inventory_account_id' => $accounts['inventory_finished'],      // Finished goods inventory
                'direct_cost_applied_account_id' => $accounts['direct_cost_applied_mat'],
                'purchase_variance_account_id' => $accounts['purchase_variance'],
            ],

            // ============================================
            // PRODUCTION POSTING (Manufacturing Group + Product Group)
            // ============================================

            // Production Output - Finished Goods
            [
                'general_business_posting_group_id' => $busGroups['MANUFACTURING'],
                'general_product_posting_group_id' => $prodGroups['FINISHED'],
                'inventory_account_id' => $accounts['inventory_finished'],      // Finished goods produced
                'inventory_adj_account_id' => $accounts['wip_account'],          // WIP clearing
                'direct_cost_applied_account_id' => $accounts['direct_cost_applied_mat'],
                'overhead_applied_account_id' => $accounts['overhead_applied'],
                'purchase_variance_account_id' => $accounts['purchase_variance'], // Production variances
            ],

            // Production Consumption - Raw Materials
            [
                'general_business_posting_group_id' => $busGroups['MANUFACTURING'],
                'general_product_posting_group_id' => $prodGroups['RAWMAT'],
                'inventory_account_id' => $accounts['inventory_rawmat'],        // Raw materials consumed
                'inventory_adj_account_id' => $accounts['wip_account'],          // To WIP
                'direct_cost_applied_account_id' => $accounts['direct_cost_applied_mat'],
            ],

            // Work in Process (WIP) Account
            [
                'general_business_posting_group_id' => $busGroups['MANUFACTURING'],
                'general_product_posting_group_id' => $prodGroups['WIP'],
                'inventory_account_id' => $accounts['wip_account'],              // WIP inventory
                'direct_cost_applied_account_id' => $accounts['direct_cost_applied_mat'],
                'overhead_applied_account_id' => $accounts['overhead_applied'],
            ],

            // Capacity/Labor posting in Production
            [
                'general_business_posting_group_id' => $busGroups['MANUFACTURING'],
                'general_product_posting_group_id' => $prodGroups['CAPACITY'],
                'inventory_adj_account_id' => $accounts['wip_account'],          // Labor to WIP
                'direct_cost_applied_account_id' => $accounts['direct_cost_applied_cap'],
                'overhead_applied_account_id' => $accounts['overhead_applied'],
            ],

            // Add the missing combination to your seeder
            [
                'general_business_posting_group_id' => 1, // DOMESTIC
                'general_product_posting_group_id' => 3,  // Whatever group 3 is
                'sales_account_id' => $accounts['sales_domestic'],
                'sales_credit_memo_account_id' => $accounts['sales_domestic'],
                'cogs_account_id' => $accounts['cogs_domestic'],
                'cogs_credit_memo_account_id' => $accounts['cogs_domestic'],
                'inventory_account_id' => $accounts['inventory_finished'],
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

    private function getBusinessPostingGroups(): array
    {
        $codes = ['DOMESTIC', 'EXPORT', 'FOREIGN', 'MANUFACTURING'];
        $groups = [];

        foreach ($codes as $code) {
            $group = GeneralBusinessPostingGroup::where('code', $code)->first();
            if (! $group) {
                throw new \Exception("GeneralBusinessPostingGroup '{$code}' not found. Run GeneralBusinessPostingGroupSeeder first.");
            }
            $groups[$code] = $group->id;
        }

        return $groups;
    }

    private function getProductPostingGroups(): array
    {
        $codes = ['RAWMAT', 'WIP', 'FINISHED', 'CAPACITY'];
        $groups = [];

        foreach ($codes as $code) {
            $group = GeneralProductPostingGroup::where('code', $code)->first();
            if (! $group) {
                throw new \Exception("GeneralProductPostingGroup '{$code}' not found. Run GeneralProductPostingGroupSeeder first.");
            }
            $groups[$code] = $group->id;
        }

        return $groups;
    }

    private function getAccounts(): array
    {
        $accountMap = [
            // Sales accounts
            'sales_domestic' => '40100',
            'sales_export' => '40200',

            // COGS accounts
            'cogs_domestic' => '50100',
            'cogs_export' => '50200',

            // Inventory accounts (ASSET accounts - Balance Sheet)
            'inventory_finished' => '13200',  // Finished Goods Inventory
            'inventory_rawmat' => '13100',    // Raw Materials Inventory
            'wip_account' => '13300',         // Work in Process

            // Cost/Variance accounts
            'direct_cost_applied_mat' => '52100',
            'direct_cost_applied_cap' => '62100',
            'overhead_applied' => '62200',
            'purchase_variance' => '50300',
            'inventory_adjustment' => '50400', // Inventory adjustment expense
        ];

        $accounts = [];

        foreach ($accountMap as $key => $accountNumber) {
            $account = ChartOfAccount::where('account_number', $accountNumber)->first();
            if (! $account) {
                throw new \Exception("ChartOfAccount '{$accountNumber}' ({$key}) not found. Run ChartOfAccountSeeder first.");
            }
            $accounts[$key] = $account->id;
        }

        return $accounts;
    }
}
