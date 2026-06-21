<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Location;
use Illuminate\Database\Seeder;

class InventoryPostingSetupSeeder extends Seeder
{
    public function run(): void
    {
        $groupByCode = InventoryPostingGroup::query()
            ->whereIn('code', ['RAW', 'PACKAGING', 'WIP', 'FINISHED', 'IN-TRANSIT'])
            ->get()
            ->keyBy('code');

        if (! $groupByCode->has('RAW') || ! $groupByCode->has('WIP') || ! $groupByCode->has('FINISHED')) {
            $this->command->error('Required Inventory Posting Groups missing. Seed InventoryPostingGroupSeeder first.');

            return;
        }

        $accountByNumber = ChartOfAccount::query()
            ->whereIn('account_number', ['13110', '13210', '13310'])
            ->get()
            ->keyBy('account_number');

        if (! $accountByNumber->has('13110') || ! $accountByNumber->has('13210') || ! $accountByNumber->has('13310')) {
            $this->command->error('Required GL accounts missing (13110, 13210, 13310). Seed ChartOfAccountSeeder first.');

            return;
        }

        $rawInventoryAccount = $accountByNumber->get('13110');
        $finishedInventoryAccount = $accountByNumber->get('13210');
        $wipAccount = $accountByNumber->get('13310');

        $defaultMap = [
            'RAW' => $rawInventoryAccount,
            'PACKAGING' => $rawInventoryAccount,
            'WIP' => $wipAccount,
            'FINISHED' => $finishedInventoryAccount,
            'IN-TRANSIT' => $rawInventoryAccount,
        ];

        foreach ($defaultMap as $groupCode => $inventoryAccount) {
            $group = $groupByCode->get($groupCode);
            if (! $group) {
                continue;
            }

            InventoryPostingSetup::updateOrCreate(
                [
                    'location_id' => null,
                    'inventory_posting_group_id' => $group->id,
                ],
                [
                    'inventory_account_id' => $inventoryAccount->id,
                    'inventory_account_interim_id' => $inventoryAccount->id,
                    'wip_account_id' => $wipAccount->id,
                ]
            );

            $this->command->info("Created default inventory posting setup for group {$groupCode}");
        }

        $inTransitGroup = $groupByCode->get('IN-TRANSIT');
        $inTransitLocation = Location::query()->where('code', 'IN-TRANSIT')->first();

        if ($inTransitGroup && $inTransitLocation) {
            InventoryPostingSetup::updateOrCreate(
                [
                    'location_id' => $inTransitLocation->id,
                    'inventory_posting_group_id' => $inTransitGroup->id,
                ],
                [
                    'inventory_account_id' => $rawInventoryAccount->id,
                    'inventory_account_interim_id' => $rawInventoryAccount->id,
                    'wip_account_id' => $wipAccount->id,
                ]
            );

            $this->command->info('Created IN-TRANSIT location-specific inventory posting setup');
        }
    }
}
