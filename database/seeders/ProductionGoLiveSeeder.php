<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductionGoLiveSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            /*
             * Core reference and organizational setup
             */
            LocationSeeder::class,
            UnitOfMeasureSeeder::class,
            CurrencySeeder::class,
            ReasonCodeSeeder::class,

            /*
             * Financial foundation
             */
            ChartOfAccountSeeder::class,
            //            GlAccountSeeder::class,

            /*
             * VAT and posting groups
             */
            VatPostingSeeder::class,
            InventoryPostingGroupSeeder::class,
            GeneralBusinessPostingGroupSeeder::class,
            GeneralProductPostingGroupSeeder::class,
            VendorPostingGroupSeeder::class,
            CustomerPostingGroupSeeder::class,

            /*
             * Posting setup
             *
             * These must run after accounts, posting groups and locations.
             */
            GeneralPostingSetupSeeder::class,
            InventoryPostingSetupSeeder::class,

            /*
             * Inventory master data
             */
            CategorySeeder::class,
            ItemSeeder::class,
            NumberSeriesSeeder::class,

            /*
             * Payroll and HR setup
             */
            PayrollPostingGroupSeeder::class,
            PayrollSetupSeeder::class,
            PayrollPeriodSeeder::class,
            LeaveTypeSeeder::class,

            /*
             * Manufacturing setup
             */
            ProductionShopFloorSetupSeeder::class,
            OverheadCostCategorySeeder::class,

            /*
             * Fixed asset setup
             */
            FAPostingGroupSeeder::class,
            DepreciationBookSeeder::class,
            FAClassSeeder::class,

            /*
             * Security
             */
            PermissionsTableSeeder::class,
            RolesTableSeeder::class,
            UsersTableSeeder::class,
            RolePermissionSetSeeder::class,
        ]);
    }
}
