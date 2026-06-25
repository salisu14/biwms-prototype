<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            LocationSeeder::class,
            UnitOfMeasureSeeder::class,
            ChartOfAccountSeeder::class,
            ReasonCodeSeeder::class,
            CurrencySeeder::class,
            GlAccountSeeder::class,
            VatPostingSeeder::class,
            InventoryPostingGroupSeeder::class,
            InventoryPostingSetupSeeder::class,
            GeneralBusinessPostingGroupSeeder::class,
            GeneralProductPostingGroupSeeder::class,
            GeneralPostingSetupSeeder::class,
            VendorPostingGroupSeeder::class,
            CustomerPostingGroupSeeder::class,
            CustomerSeeder::class,
            ItemSeeder::class,
            VendorSeeder::class,
            NumberSeriesSeeder::class,
            PayrollPostingGroupSeeder::class,
            PayrollSetupSeeder::class,
            PayrollPeriodSeeder::class,
            PermissionsTableSeeder::class,
            RolesTableSeeder::class,
            UsersTableSeeder::class,
            RolePermissionSetSeeder::class,
            OverheadCostCategorySeeder::class,
            FAPostingGroupSeeder::class,
            DepreciationBookSeeder::class,
            FAClassSeeder::class,
            FixedAssetSampleSeeder::class,
            BalanceSheetAccountScheduleSeeder::class,
            ProfitAndLossAccountScheduleSeeder::class,
            CashFlowStatementAccountScheduleSeeder::class,
        ]);
    }
}
