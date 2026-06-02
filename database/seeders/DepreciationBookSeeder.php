<?php

namespace Database\Seeders;

use App\Enums\DepreciationCalculationMethod;
use App\Enums\DepreciationMethod;
use App\Models\DepreciationBook;
use Illuminate\Database\Seeder;

class DepreciationBookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DepreciationBook::updateOrCreate(
            ['code' => 'CORP'],
            [
                'description' => 'Corporate Depreciation Book',
                'book_type' => 'corporate',
                'is_default' => true,
                'default_depreciation_method' => DepreciationMethod::STRAIGHT_LINE,
                'default_calculation_method' => DepreciationCalculationMethod::STRAIGHT_LINE,
                'integrate_with_gl' => true,
                'use_rounding' => true,
                'rounding_precision' => 2,
                'align_fiscal_year' => true,
                'fiscal_year_start' => 1,
                'is_active' => true,
            ]
        );
    }
}
