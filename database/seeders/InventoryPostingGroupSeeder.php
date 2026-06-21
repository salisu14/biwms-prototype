<?php

namespace Database\Seeders;

use App\Models\InventoryPostingGroup;
use Illuminate\Database\Seeder;

class InventoryPostingGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groups = [
            [
                'code' => 'RAW',
                'description' => 'Raw Materials',
                'blocked' => false,
            ],
            [
                'code' => 'PACKAGING',
                'description' => 'Packing Materials',
                'blocked' => false,
            ],
            [
                'code' => 'WIP',
                'description' => 'Work in Process',
                'blocked' => false,
            ],
            [
                'code' => 'FINISHED',
                'description' => 'Finished Goods',
                'blocked' => false,
            ],
            [
                'code' => 'IN-TRANSIT',
                'description' => 'Goods in Transit',
                'blocked' => false,
            ],
        ];

        foreach ($groups as $group) {
            InventoryPostingGroup::updateOrCreate(
                ['code' => $group['code']],
                $group
            );
        }
    }
}
