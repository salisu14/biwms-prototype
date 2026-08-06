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
            ['code' => 'INDIRECT_LABOR', 'name' => 'Indirect Labor', 'is_active' => true],
            ['code' => 'MAINTENANCE', 'name' => 'Maintenance', 'is_active' => true],
            ['code' => 'UTILITIES', 'name' => 'Utilities', 'is_active' => true],
            ['code' => 'RENT', 'name' => 'Rent/Lease', 'is_active' => true],
            ['code' => 'DEPRECIATION', 'name' => 'Depreciation', 'is_active' => true],
            ['code' => 'INSURANCE', 'name' => 'Insurance', 'is_active' => true],
            ['code' => 'OTHER', 'name' => 'Other Indirect', 'is_active' => true],
            ['code' => 'TAXES', 'name' => 'Taxes & Fees', 'is_active' => true],
            ['code' => 'SUPPLIES', 'name' => 'Indirect Supplies', 'is_active' => true],
        ];

        foreach ($categories as $category) {
            OverheadCostCategory::query()->updateOrCreate(
                ['code' => $category['code']],
                [
                    'name' => $category['name'],
                    'is_active' => $category['is_active'],
                ],
            );
        }
    }
}
