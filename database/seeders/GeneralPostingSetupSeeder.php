<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralProductPostingGroup;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
            ],

            // Export Sales of Finished Goods
            [
                'general_business_posting_group_id' => $busGroups['EXPORT'],
                'general_product_posting_group_id' => $prodGroups['FINISHED'],
                'sales_account_id' => $accounts['sales_export'],
                'sales_credit_memo_account_id' => $accounts['sales_export'],
                'cogs_account_id' => $accounts['cogs_export'],
                'cogs_credit_memo_account_id' => $accounts['cogs_export'],
            ],

            // ============================================
            // PURCHASE POSTING (Vendor Group + Product Group)
            // ============================================

            // Purchase of Raw Materials - Domestic
            [
                'general_business_posting_group_id' => $busGroups['DOMESTIC'],
                'general_product_posting_group_id' => $prodGroups['RAWMAT'],
                'sales_account_id' => null,
                'sales_credit_memo_account_id' => null,
                'cogs_account_id' => null,
                'cogs_credit_memo_account_id' => null,
                'inventory_adj_account_id' => $accounts['direct_cost_applied_mat'],
                'direct_cost_applied_account_id' => $accounts['direct_cost_applied_mat'],
                'overhead_applied_account_id' => null,
            ],

            // Purchase of Raw Materials - Import
            [
                'general_business_posting_group_id' => $busGroups['FOREIGN'],
                'general_product_posting_group_id' => $prodGroups['RAWMAT'],
                'sales_account_id' => null,
                'sales_credit_memo_account_id' => null,
                'cogs_account_id' => null,
                'cogs_credit_memo_account_id' => null,
                'inventory_adj_account_id' => $accounts['direct_cost_applied_mat'],
                'direct_cost_applied_account_id' => $accounts['direct_cost_applied_mat'],
                'overhead_applied_account_id' => null,
            ],

            // ============================================
            // PRODUCTION POSTING (Manufacturing Group + Product Group)
            // ============================================

            // Production of Finished Goods from Raw Materials
            [
                'general_business_posting_group_id' => $busGroups['MANUFACTURING'],
                'general_product_posting_group_id' => $prodGroups['FINISHED'],
                'sales_account_id' => null,
                'sales_credit_memo_account_id' => null,
                'cogs_account_id' => null,
                'cogs_credit_memo_account_id' => null,
                'inventory_adj_account_id' => $accounts['purchase_variance'],
                'direct_cost_applied_account_id' => $accounts['direct_cost_applied_mat'],
                'overhead_applied_account_id' => $accounts['overhead_applied'],
            ],

            // Production Components (Raw Materials being consumed)
            [
                'general_business_posting_group_id' => $busGroups['MANUFACTURING'],
                'general_product_posting_group_id' => $prodGroups['RAWMAT'],
                'sales_account_id' => null,
                'sales_credit_memo_account_id' => null,
                'cogs_account_id' => null,
                'cogs_credit_memo_account_id' => null,
                'inventory_adj_account_id' => null,
                'direct_cost_applied_account_id' => $accounts['direct_cost_applied_mat'],
                'overhead_applied_account_id' => null,
            ],

            // Capacity/Labor posting in Production
            [
                'general_business_posting_group_id' => $busGroups['MANUFACTURING'],
                'general_product_posting_group_id' => $prodGroups['CAPACITY'],
                'sales_account_id' => null,
                'sales_credit_memo_account_id' => null,
                'cogs_account_id' => null,
                'cogs_credit_memo_account_id' => null,
                'inventory_adj_account_id' => null,
                'direct_cost_applied_account_id' => $accounts['direct_cost_applied_cap'],
                'overhead_applied_account_id' => $accounts['overhead_applied'],
            ],
        ];

        foreach ($setups as $setup) {
            // Filter out null values to avoid overwriting existing data
            $data = array_filter($setup, fn($value) => $value !== null);

            GeneralPostingSetup::updateOrCreate(
                [
                    'general_business_posting_group_id' => $setup['general_business_posting_group_id'],
                    'general_product_posting_group_id' => $setup['general_product_posting_group_id'],
                ],
                $data
            );
        }
    }

    private function getBusinessPostingGroups(): array
    {
        $codes = ['DOMESTIC', 'EXPORT', 'FOREIGN', 'MANUFACTURING'];
        $groups = [];

        foreach ($codes as $code) {
            $group = GeneralBusinessPostingGroup::where('code', $code)->first();
            if (!$group) {
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
            if (!$group) {
                throw new \Exception("GeneralProductPostingGroup '{$code}' not found. Run GeneralProductPostingGroupSeeder first.");
            }
            $groups[$code] = $group->id;
        }

        return $groups;
    }

    private function getAccounts(): array
    {
        $accountMap = [
            'cogs_domestic' => '50100',
            'cogs_export' => '50200',
            'sales_domestic' => '40100',
            'sales_export' => '40200',
            'direct_cost_applied_mat' => '52100',
            'direct_cost_applied_cap' => '62100',
            'overhead_applied' => '62200',
            'purchase_variance' => '50300',
        ];

        $accounts = [];

        foreach ($accountMap as $key => $accountNumber) {
            $account = ChartOfAccount::where('account_number', $accountNumber)->first();
            if (!$account) {
                throw new \Exception("ChartOfAccount '{$accountNumber}' not found. Run ChartOfAccountSeeder first.");
            }
            $accounts[$key] = $account->id;
        }

        return $accounts;
    }
}
