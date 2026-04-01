<?php

namespace Database\Seeders;

use App\Models\User;
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
            GeneralBusinessPostingGroupSeeder::class,
            VendorPostingGroupSeeder::class,
            CustomerPostingGroupSeeder::class,
            CustomerSeeder::class,
            VendorSeeder::class,
            NumberSeriesSeeder::class,
            VatMasterSeeder::class,
            PermissionsTableSeeder::class,
            RolesTableSeeder::class,
            UsersTableSeeder::class,
        ]);
    }
}
