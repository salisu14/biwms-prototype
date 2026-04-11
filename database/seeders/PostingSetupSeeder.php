<?php

// database/seeders/PostingSetupSeeder.php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralPostingSetupLine;
use App\Models\GeneralProductPostingGroup;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Location;
use Illuminate\Database\Seeder;

class PostingSetupSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Chart of Accounts
        $revenueRetail = ChartOfAccount::create([
            'account_number' => '40100',
            'name' => 'Sales - Domestic Retail',
            'account_type' => 'REVENUE',
            'account_category' => 'REVENUE',
            'direct_posting' => false,
        ]);

        $cogsRetail = ChartOfAccount::create([
            'account_number' => '50100',
            'name' => 'COGS - Domestic Retail',
            'account_type' => 'COGS',
            'account_category' => 'COGS',
            'direct_posting' => false,
        ]);

        $inventoryFinished = ChartOfAccount::create([
            'account_number' => '14100',
            'name' => 'Inventory - Finished Goods',
            'account_type' => 'ASSET',
            'account_category' => 'INVENTORY',
            'direct_posting' => false,
        ]);

        // 2. Create Posting Groups
        $domestic = GeneralBusinessPostingGroup::create([
            'code' => 'DOMESTIC',
            'description' => 'Domestic Customers and Vendors',
        ]);

        $retail = GeneralProductPostingGroup::create([
            'code' => 'RETAIL',
            'description' => 'Retail Finished Goods',
        ]);

        $finished = InventoryPostingGroup::create([
            'code' => 'FINISHED',
            'description' => 'Finished Goods',
        ]);

        // 3. Create General Posting Setup Matrix
        $setup = GeneralPostingSetup::create([
            'general_business_posting_group_id' => $domestic->id,
            'general_product_posting_group_id' => $retail->id,
        ]);

        // 4. Assign Accounts to Setup
        GeneralPostingSetupLine::create([
            'general_posting_setup_id' => $setup->id,
            'line_type' => 'SALES',
            'chart_of_account_id' => $revenueRetail->id,
        ]);

        GeneralPostingSetupLine::create([
            'general_posting_setup_id' => $setup->id,
            'line_type' => 'COGS',
            'chart_of_account_id' => $cogsRetail->id,
        ]);

        // 5. Create Location and Inventory Setup
        $mainLocation = Location::create([
            'code' => 'MAIN',
            'name' => 'Main Warehouse',
            'directed_put_away_and_pick' => false,
            'bin_mandatory' => false,
        ]);

        InventoryPostingSetup::create([
            'location_id' => null, // Default for all locations
            'inventory_posting_group_id' => $finished->id,
            'inventory_account_id' => $inventoryFinished->id,
        ]);
    }
}
