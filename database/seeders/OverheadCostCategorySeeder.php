<?php

namespace Database\Seeders;

use App\Models\OverheadCostCategory;
use Illuminate\Database\Seeder;

class OverheadCostCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['code' => 'indirect_labor', 'name' => 'Indirect Labor', 'is_active' => true],
            ['code' => 'maintenance',    'name' => 'Maintenance',    'is_active' => true],
            ['code' => 'utilities',      'name' => 'Utilities',      'is_active' => true],
            ['code' => 'rent',           'name' => 'Rent/Lease',     'is_active' => true],
            ['code' => 'depreciation',   'name' => 'Depreciation',   'is_active' => true],
            ['code' => 'insurance',      'name' => 'Insurance',      'is_active' => true],
            ['code' => 'other',          'name' => 'Other Indirect', 'is_active' => true],
            ['code' => 'taxes',          'name' => 'Taxes & Fees',   'is_active' => true],
            ['code' => 'supplies',       'name' => 'Indirect Supplies', 'is_active' => true],
        ];

        foreach ($categories as $category) {
            OverheadCostCategory::updateOrCreate(
                ['code' => $category['code']],
                $category
            );
        }
    }
}
