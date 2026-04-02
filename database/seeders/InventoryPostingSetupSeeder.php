<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InventoryPostingSetup;
use App\Models\ChartOfAccount;
use App\Models\Location;
use App\Models\InventoryPostingGroup;

class InventoryPostingSetupSeeder extends Seeder
{
    public function run(): void
    {
        // Get posting groups by code
        $rawGroup = InventoryPostingGroup::where('code', 'RAW')->first();
        $wipGroup = InventoryPostingGroup::where('code', 'WIP')->first();
        $finishedGroup = InventoryPostingGroup::where('code', 'FINISHED')->first();
        $inTransitGroup = InventoryPostingGroup::where('code', 'IN-TRANSIT')->first();

        if (!$rawGroup || !$wipGroup || !$finishedGroup) {
            $this->command->error('Required InventoryPostingGroups not found. Run InventoryPostingGroupSeeder first.');
            return;
        }

        // Get accounts by account_number
        $rawMatAccount = ChartOfAccount::where('account_number', '21110')->first();
        $wipAccount = ChartOfAccount::where('account_number', '21310')->first();
        $finishedAccount = ChartOfAccount::where('account_number', '21410')->first();

        if (!$rawMatAccount || !$wipAccount || !$finishedAccount) {
            $this->command->error('Required ChartOfAccounts not found. Run ChartOfAccountSeeder first.');
            return;
        }

        // Get locations by code
        $rawWarehouse = Location::where('code', 'RAW-WAREHOUSE')->first();
        $prodFloor = Location::where('code', 'PROD-FLOOR')->first();
        $fgWarehouseA = Location::where('code', 'FG-WAREHOUSE-A')->first();
        $fgWarehouseB = Location::where('code', 'FG-WAREHOUSE-B')->first();
        $inTransit = Location::where('code', 'IN-TRANSIT')->first();

        $setups = [
            [
                'location' => $rawWarehouse,
                'group' => $rawGroup,
                'inventory_account' => $rawMatAccount,
                'inventory_account_interim' => $rawMatAccount,
                'wip_account' => $wipAccount,
            ],
            [
                'location' => $prodFloor,
                'group' => $wipGroup,
                'inventory_account' => $wipAccount,
                'inventory_account_interim' => $wipAccount,
                'wip_account' => $wipAccount,
            ],
            [
                'location' => $fgWarehouseA,
                'group' => $finishedGroup,
                'inventory_account' => $finishedAccount,
                'inventory_account_interim' => $finishedAccount,
                'wip_account' => $wipAccount,
            ],
            [
                'location' => $fgWarehouseB,
                'group' => $finishedGroup,
                'inventory_account' => $finishedAccount,
                'inventory_account_interim' => $finishedAccount,
                'wip_account' => $wipAccount,
            ],
        ];

        if ($inTransit && $inTransitGroup) {
            $setups[] = [
                'location' => $inTransit,
                'group' => $inTransitGroup,
                'inventory_account' => $rawMatAccount,
                'inventory_account_interim' => $rawMatAccount,
                'wip_account' => $wipAccount,
            ];
        }

        foreach ($setups as $setup) {
            if (!$setup['location']) {
                $this->command->warn("Skipping setup: location not found");
                continue;
            }

            InventoryPostingSetup::updateOrCreate(
                [
                    'location_id' => $setup['location']->id,
                    'inventory_posting_group_id' => $setup['group']->id,
                ],
                [
                    'inventory_account_id' => $setup['inventory_account']->id,
                    'inventory_account_interim_id' => $setup['inventory_account_interim']->id,
                    'wip_account_id' => $setup['wip_account']->id,
                ]
            );

            $this->command->info("Created setup: {$setup['location']->code} / {$setup['group']->code}");
        }

        // Create default (no location) for RAW
        InventoryPostingSetup::updateOrCreate(
            [
                'location_id' => null,
                'inventory_posting_group_id' => $rawGroup->id,
            ],
            [
                'inventory_account_id' => $rawMatAccount->id,
                'inventory_account_interim_id' => $rawMatAccount->id,
                'wip_account_id' => $wipAccount->id,
            ]
        );

        $this->command->info("Created default setup for RAW");
    }
}
