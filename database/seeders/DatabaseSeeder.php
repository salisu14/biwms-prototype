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
            CurrencySeeder::class,
            BankAccountSeeder::class,
            GlAccountSeeder::class,
            VatPostingSeeder::class,
            InventoryPostingGroupSeeder::class,
            InventoryPostingSetupSeeder::class,
            GeneralBusinessPostingGroupSeeder::class,
            GeneralProductPostingGroupSeeder::class,
            GeneralPostingSetupSeeder::class,
            VendorPostingGroupSeeder::class,
            CustomerPostingGroupSeeder::class,
            //            ContactSeeder::class,
            CustomerSeeder::class,
            ItemSeeder::class,
            VendorSeeder::class,
            NumberSeriesSeeder::class,
            // VatMasterSeeder::class, // Replaced by VatPostingSeeder
            PermissionsTableSeeder::class,
            RolesTableSeeder::class,
            UsersTableSeeder::class,
        ]);
    }
}
