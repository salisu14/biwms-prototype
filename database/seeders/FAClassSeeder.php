<?php

namespace Database\Seeders;

use App\Enums\FixedAssetType;
use App\Models\FAClass;
use App\Models\FAPostingGroup;
use Illuminate\Database\Seeder;

class FAClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $classes = [
            ['code' => 'MACHINERY', 'name' => 'Machinery', 'posting_group' => 'MACHINERY'],
            ['code' => 'VEHICLES', 'name' => 'Vehicles', 'posting_group' => 'VEHICLES'],
        ];

        foreach ($classes as $class) {
            FAClass::updateOrCreate(
                ['code' => $class['code']],
                [
                    'name' => $class['name'],
                    'fa_type' => FixedAssetType::TANGIBLE,
                    'default_posting_group_id' => FAPostingGroup::query()->where('code', $class['posting_group'])->value('id'),
                    'is_active' => true,
                ]
            );
        }
    }
}
