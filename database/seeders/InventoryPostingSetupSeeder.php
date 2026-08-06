<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Location;
use Illuminate\Database\Seeder;
use RuntimeException;

class InventoryPostingSetupSeeder extends Seeder
{
    public function run(): void
    {
        $groupByCode = InventoryPostingGroup::query()
            ->whereIn(
                'code',
                [
                    'RAW',
                    'PACKAGING',
                    'WIP',
                    'FINISHED',
                    'IN-TRANSIT',
                ],
            )
            ->get()
            ->keyBy('code');

        $requiredGroups = [
            'RAW',
            'PACKAGING',
            'WIP',
            'FINISHED',
            'IN-TRANSIT',
        ];

        $missingGroups = collect($requiredGroups)
            ->reject(
                fn (string $code): bool => $groupByCode->has($code),
            )
            ->values();

        if ($missingGroups->isNotEmpty()) {
            throw new RuntimeException(
                'Required Inventory Posting Groups are missing: '
                .$missingGroups->implode(', ')
                .'. Run InventoryPostingGroupSeeder first.',
            );
        }

        /*
         * Existing BIWMS account mapping:
         *
         * 13110 → Raw Materials / Packaging / In Transit Inventory
         * 13210 → Finished Goods Inventory
         * 13310 → Work in Process Inventory
         *
         * Adjust these numbers only when the approved Chart of Accounts
         * uses different inventory-control accounts.
         */
        $accountByNumber = ChartOfAccount::query()
            ->whereIn(
                'account_number',
                [
                    '13110',
                    '13210',
                    '13310',
                ],
            )
            ->get()
            ->keyBy('account_number');

        $requiredAccounts = [
            '13110',
            '13210',
            '13310',
        ];

        $missingAccounts = collect($requiredAccounts)
            ->reject(
                fn (string $number): bool => $accountByNumber->has($number),
            )
            ->values();

        if ($missingAccounts->isNotEmpty()) {
            throw new RuntimeException(
                'Required Inventory G/L accounts are missing: '
                .$missingAccounts->implode(', ')
                .'. Run ChartOfAccountSeeder first.',
            );
        }

        $rawInventoryAccount = $accountByNumber->get('13110');
        $finishedInventoryAccount = $accountByNumber->get('13210');
        $wipInventoryAccount = $accountByNumber->get('13310');

        /*
         * Default setup by Inventory Posting Group.
         *
         * These remain useful as controlled fallbacks when a document does
         * not have a location-specific setup.
         */
        $defaultSetups = [
            'RAW' => $rawInventoryAccount,
            'PACKAGING' => $rawInventoryAccount,
            'WIP' => $wipInventoryAccount,
            'FINISHED' => $finishedInventoryAccount,
            'IN-TRANSIT' => $rawInventoryAccount,
        ];

        foreach ($defaultSetups as $groupCode => $inventoryAccount) {
            $group = $groupByCode->get($groupCode);

            InventoryPostingSetup::query()->updateOrCreate(
                [
                    'location_id' => null,
                    'inventory_posting_group_id' => $group->id,
                ],
                [
                    'inventory_account_id' => $inventoryAccount->id,
                    'inventory_account_interim_id' => $inventoryAccount->id,
                    'wip_account_id' => $wipInventoryAccount->id,
                ],
            );

            $this->command?->info(
                "Created default Inventory Posting Setup for {$groupCode}.",
            );
        }

        /*
         * Location-specific setup.
         *
         * Every tuple is:
         * [Inventory Posting Group, Location, Inventory Account]
         */
        $locationSetups = [
            /*
             * Raw-material warehouse.
             */
            [
                'group' => 'RAW',
                'location' => 'GBS-RAWMAT',
                'account' => $rawInventoryAccount,
            ],

            /*
             * Packaging-material warehouse.
             */
            [
                'group' => 'PACKAGING',
                'location' => 'GBS-PACK',
                'account' => $rawInventoryAccount,
            ],

            /*
             * Finished-goods warehouse.
             */
            [
                'group' => 'FINISHED',
                'location' => 'GBS-FGN',
                'account' => $finishedInventoryAccount,
            ],

            /*
             * Intermediate/semi-finished inventory.
             */
            [
                'group' => 'WIP',
                'location' => 'GBS-WIP',
                'account' => $wipInventoryAccount,
            ],

            /*
             * Production floor.
             *
             * Materials issued to the floor retain their relevant inventory
             * classification until consumption is posted.
             */
            [
                'group' => 'RAW',
                'location' => 'GBS-PROD',
                'account' => $rawInventoryAccount,
            ],
            [
                'group' => 'PACKAGING',
                'location' => 'GBS-PROD',
                'account' => $rawInventoryAccount,
            ],
            [
                'group' => 'WIP',
                'location' => 'GBS-PROD',
                'account' => $wipInventoryAccount,
            ],

            /*
             * Quality-control and quarantine locations.
             *
             * The Item's posting group continues to determine whether the
             * balance is raw, packaging, WIP or finished inventory.
             */
            [
                'group' => 'RAW',
                'location' => 'GBS-QC',
                'account' => $rawInventoryAccount,
            ],
            [
                'group' => 'PACKAGING',
                'location' => 'GBS-QC',
                'account' => $rawInventoryAccount,
            ],
            [
                'group' => 'WIP',
                'location' => 'GBS-QC',
                'account' => $wipInventoryAccount,
            ],
            [
                'group' => 'FINISHED',
                'location' => 'GBS-QC',
                'account' => $finishedInventoryAccount,
            ],
            [
                'group' => 'RAW',
                'location' => 'GBS-QUAR',
                'account' => $rawInventoryAccount,
            ],
            [
                'group' => 'PACKAGING',
                'location' => 'GBS-QUAR',
                'account' => $rawInventoryAccount,
            ],
            [
                'group' => 'WIP',
                'location' => 'GBS-QUAR',
                'account' => $wipInventoryAccount,
            ],
            [
                'group' => 'FINISHED',
                'location' => 'GBS-QUAR',
                'account' => $finishedInventoryAccount,
            ],

            /*
             * Dispatch, returns and scrap.
             */
            [
                'group' => 'FINISHED',
                'location' => 'GBS-DISPATCH',
                'account' => $finishedInventoryAccount,
            ],
            [
                'group' => 'FINISHED',
                'location' => 'GBS-RETURNS',
                'account' => $finishedInventoryAccount,
            ],
            [
                'group' => 'RAW',
                'location' => 'GBS-SCRAP',
                'account' => $rawInventoryAccount,
            ],
            [
                'group' => 'PACKAGING',
                'location' => 'GBS-SCRAP',
                'account' => $rawInventoryAccount,
            ],
            [
                'group' => 'WIP',
                'location' => 'GBS-SCRAP',
                'account' => $wipInventoryAccount,
            ],
            [
                'group' => 'FINISHED',
                'location' => 'GBS-SCRAP',
                'account' => $finishedInventoryAccount,
            ],

            /*
             * Existing in-transit location, where available.
             */
            [
                'group' => 'IN-TRANSIT',
                'location' => 'IN-TRANSIT',
                'account' => $rawInventoryAccount,
                'optional' => true,
            ],
        ];

        $createdLocationSpecificCount = 0;
        $skippedOptionalCount = 0;

        foreach ($locationSetups as $setup) {
            $group = $groupByCode->get($setup['group']);

            $location = Location::query()
                ->where('code', $setup['location'])
                ->first();

            if ($location === null) {
                if ($setup['optional'] ?? false) {
                    $skippedOptionalCount++;

                    $this->command?->warn(
                        "Optional location {$setup['location']} was not "
                        .'found; its posting setup was skipped.',
                    );

                    continue;
                }

                throw new RuntimeException(
                    "Required Location {$setup['location']} was not found. "
                    .'Run LocationSeeder before '
                    .'InventoryPostingSetupSeeder.',
                );
            }

            InventoryPostingSetup::query()->updateOrCreate(
                [
                    'location_id' => $location->id,
                    'inventory_posting_group_id' => $group->id,
                ],
                [
                    'inventory_account_id' => $setup['account']->id,
                    'inventory_account_interim_id' => $setup['account']->id,
                    'wip_account_id' => $wipInventoryAccount->id,
                ],
            );

            $createdLocationSpecificCount++;

            $this->command?->info(
                'Created Inventory Posting Setup for '
                ."{$setup['group']} + {$setup['location']}.",
            );
        }

        $this->command?->info(
            'Inventory Posting Setups seeded successfully.',
        );

        $this->command?->info(
            'Default setups: '.count($defaultSetups),
        );

        $this->command?->info(
            'Location-specific setups: '
            .$createdLocationSpecificCount,
        );

        $this->command?->info(
            'Optional setups skipped: '.$skippedOptionalCount,
        );
    }
}
