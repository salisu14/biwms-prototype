<?php

namespace Database\Seeders;

use App\Enums\DimensionValueType;
use App\Models\Dimension;
use App\Models\DimensionValue;
use Illuminate\Database\Seeder;

class OrgDimensionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create BUSINESS Dimension
        $businessDim = Dimension::updateOrCreate(
            ['code' => 'BUSINESS'],
            [
                'name' => 'Business Group',
                'dimension_type' => 'global',
                'global_dimension_no' => 1,
            ]
        );

        $north = DimensionValue::updateOrCreate(
            ['dimension_id' => $businessDim->id, 'code' => 'NORTH'],
            ['name' => 'North Operations', 'dimension_value_type' => DimensionValueType::Standard]
        );

        $south = DimensionValue::updateOrCreate(
            ['dimension_id' => $businessDim->id, 'code' => 'SOUTH'],
            ['name' => 'South Operations', 'dimension_value_type' => DimensionValueType::Standard]
        );

        // 2. Create FACTORY Dimension
        $factoryDim = Dimension::updateOrCreate(
            ['code' => 'FACTORY'],
            [
                'name' => 'Manufacturing Factory',
                'dimension_type' => 'shortcut',
            ]
        );

        DimensionValue::updateOrCreate(
            ['dimension_id' => $factoryDim->id, 'code' => 'ALPHA'],
            [
                'name' => 'Factory Alpha',
                'dimension_value_type' => DimensionValueType::Standard,
                'parent_id' => $north->id,
            ]
        );

        DimensionValue::updateOrCreate(
            ['dimension_id' => $factoryDim->id, 'code' => 'BETA'],
            [
                'name' => 'Factory Beta',
                'dimension_value_type' => DimensionValueType::Standard,
                'parent_id' => $north->id,
            ]
        );

        DimensionValue::updateOrCreate(
            ['dimension_id' => $factoryDim->id, 'code' => 'GAMMA'],
            [
                'name' => 'Factory Gamma',
                'dimension_value_type' => DimensionValueType::Standard,
                'parent_id' => $south->id,
            ]
        );

        // 3. Create DEPARTMENT Dimension
        $deptDim = Dimension::updateOrCreate(
            ['code' => 'DEPARTMENT'],
            [
                'name' => 'Department',
                'dimension_type' => 'global',
                'global_dimension_no' => 2,
            ]
        );

        $departments = [
            'HR' => 'Human Resources',
            'IT' => 'Information Technology',
            'PROD' => 'Production',
            'SALES' => 'Sales & Marketing',
            'FIN' => 'Finance',
        ];

        foreach ($departments as $code => $name) {
            DimensionValue::updateOrCreate(
                ['dimension_id' => $deptDim->id, 'code' => $code],
                ['name' => $name, 'dimension_value_type' => DimensionValueType::Standard]
            );
        }
    }
}
