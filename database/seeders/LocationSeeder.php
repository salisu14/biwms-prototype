<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            // Main Distribution Center - Full WMS
            [
                'code' => 'GBS-RAWMAT',
                'name' => 'Gabasawa Raw Materials Store',
                'address' => 'Gabaswa Factory, Jogana',
                'directed_put_away_and_pick' => true,
                'bin_mandatory' => true,
                'require_receive' => true,
                'require_shipment' => true,
                'require_put_away' => true,
                'require_pick' => true,
                'receipt_bin_code' => 'RECEIVE-01',
                'shipment_bin_code' => 'SHIP-01',
                'open_shop_floor_bin_code' => 'SHOP-01',
                'inbound_production_bin_code' => 'PROD-IN-01',
                'outbound_production_bin_code' => 'PROD-OUT-01',
                'adjustment_bin_code' => 'ADJUST-01',
                'blocked' => false,
            ],
            // East Coast Warehouse - Full WMS
            [
                'code' => 'GBS-FGN',
                'name' => 'Gabasawa Finished Goods Store',
                'address' => 'Gabaswa Factory, Jogana',
                'directed_put_away_and_pick' => true,
                'bin_mandatory' => true,
                'require_receive' => true,
                'require_shipment' => true,
                'require_put_away' => true,
                'require_pick' => true,
                'receipt_bin_code' => 'RCV-EAST',
                'shipment_bin_code' => 'SHP-EAST',
                'open_shop_floor_bin_code' => null,
                'inbound_production_bin_code' => null,
                'outbound_production_bin_code' => null,
                'adjustment_bin_code' => 'ADJ-EAST',
                'blocked' => false,
            ],

            /*
             * Manufacturing and operational locations
             */

            // Production floor for materials issued to active production orders.
            [
                'code' => 'GBS-PROD',
                'name' => 'Gabasawa Production Floor',
                'address' => 'Gabasawa Factory, Jogana',
                'directed_put_away_and_pick' => false,
                'bin_mandatory' => true,
                'require_receive' => false,
                'require_shipment' => false,
                'require_put_away' => false,
                'require_pick' => false,
                'receipt_bin_code' => null,
                'shipment_bin_code' => null,
                'open_shop_floor_bin_code' => 'SHOP-FLOOR',
                'inbound_production_bin_code' => 'PROD-IN',
                'outbound_production_bin_code' => 'PROD-OUT',
                'adjustment_bin_code' => 'PROD-ADJ',
                'blocked' => false,
            ],

            // Intermediate and semi-finished inventory awaiting the next operation.
            [
                'code' => 'GBS-WIP',
                'name' => 'Gabasawa WIP and Intermediate Store',
                'address' => 'Gabasawa Factory, Jogana',
                'directed_put_away_and_pick' => false,
                'bin_mandatory' => true,
                'require_receive' => false,
                'require_shipment' => false,
                'require_put_away' => false,
                'require_pick' => false,
                'receipt_bin_code' => null,
                'shipment_bin_code' => null,
                'open_shop_floor_bin_code' => 'WIP-OPEN',
                'inbound_production_bin_code' => 'WIP-IN',
                'outbound_production_bin_code' => 'WIP-OUT',
                'adjustment_bin_code' => 'WIP-ADJ',
                'blocked' => false,
            ],

            // Packaging materials such as labels, caps, trays, sleeves and cartons.
            [
                'code' => 'GBS-PACK',
                'name' => 'Gabasawa Packaging Materials Store',
                'address' => 'Gabasawa Factory, Jogana',
                'directed_put_away_and_pick' => true,
                'bin_mandatory' => true,
                'require_receive' => true,
                'require_shipment' => false,
                'require_put_away' => true,
                'require_pick' => true,
                'receipt_bin_code' => 'PACK-RCV',
                'shipment_bin_code' => null,
                'open_shop_floor_bin_code' => 'PACK-FLOOR',
                'inbound_production_bin_code' => 'PACK-IN',
                'outbound_production_bin_code' => 'PACK-OUT',
                'adjustment_bin_code' => 'PACK-ADJ',
                'blocked' => false,
            ],

            // Materials or products awaiting quality inspection.
            [
                'code' => 'GBS-QC',
                'name' => 'Gabasawa Quality Control Store',
                'address' => 'Gabasawa Factory, Jogana',
                'directed_put_away_and_pick' => false,
                'bin_mandatory' => true,
                'require_receive' => false,
                'require_shipment' => false,
                'require_put_away' => false,
                'require_pick' => false,
                'receipt_bin_code' => 'QC-RCV',
                'shipment_bin_code' => null,
                'open_shop_floor_bin_code' => 'QC-TEST',
                'inbound_production_bin_code' => 'QC-IN',
                'outbound_production_bin_code' => 'QC-OUT',
                'adjustment_bin_code' => 'QC-ADJ',
                'blocked' => false,
            ],

            // Stock that must not be consumed or sold until released.
            [
                'code' => 'GBS-QUAR',
                'name' => 'Gabasawa Quarantine Store',
                'address' => 'Gabasawa Factory, Jogana',
                'directed_put_away_and_pick' => false,
                'bin_mandatory' => true,
                'require_receive' => false,
                'require_shipment' => false,
                'require_put_away' => false,
                'require_pick' => false,
                'receipt_bin_code' => 'QUAR-RCV',
                'shipment_bin_code' => null,
                'open_shop_floor_bin_code' => null,
                'inbound_production_bin_code' => 'QUAR-IN',
                'outbound_production_bin_code' => 'QUAR-OUT',
                'adjustment_bin_code' => 'QUAR-ADJ',
                'blocked' => false,
            ],

            // Finished products staged for customer delivery or transfer.
            [
                'code' => 'GBS-DISPATCH',
                'name' => 'Gabasawa Dispatch and Staging Area',
                'address' => 'Gabasawa Factory, Jogana',
                'directed_put_away_and_pick' => false,
                'bin_mandatory' => true,
                'require_receive' => false,
                'require_shipment' => true,
                'require_put_away' => false,
                'require_pick' => true,
                'receipt_bin_code' => null,
                'shipment_bin_code' => 'DISPATCH',
                'open_shop_floor_bin_code' => null,
                'inbound_production_bin_code' => null,
                'outbound_production_bin_code' => null,
                'adjustment_bin_code' => 'DISP-ADJ',
                'blocked' => false,
            ],

            // Customer returns awaiting inspection and disposition.
            [
                'code' => 'GBS-RETURNS',
                'name' => 'Gabasawa Returns Store',
                'address' => 'Gabasawa Factory, Jogana',
                'directed_put_away_and_pick' => false,
                'bin_mandatory' => true,
                'require_receive' => true,
                'require_shipment' => false,
                'require_put_away' => false,
                'require_pick' => false,
                'receipt_bin_code' => 'RETURN-RCV',
                'shipment_bin_code' => null,
                'open_shop_floor_bin_code' => null,
                'inbound_production_bin_code' => null,
                'outbound_production_bin_code' => null,
                'adjustment_bin_code' => 'RETURN-ADJ',
                'blocked' => false,
            ],

            // Engineering spares, tools and maintenance consumables.
            [
                'code' => 'GBS-MAINT',
                'name' => 'Gabasawa Maintenance and Spare Parts Store',
                'address' => 'Gabasawa Factory, Jogana',
                'directed_put_away_and_pick' => false,
                'bin_mandatory' => true,
                'require_receive' => true,
                'require_shipment' => false,
                'require_put_away' => false,
                'require_pick' => true,
                'receipt_bin_code' => 'MAINT-RCV',
                'shipment_bin_code' => null,
                'open_shop_floor_bin_code' => 'MAINT-ISSUE',
                'inbound_production_bin_code' => null,
                'outbound_production_bin_code' => null,
                'adjustment_bin_code' => 'MAINT-ADJ',
                'blocked' => false,
            ],

            // Rejected, damaged or scrapped inventory pending final disposal.
            [
                'code' => 'GBS-SCRAP',
                'name' => 'Gabasawa Scrap and Rejected Materials Store',
                'address' => 'Gabasawa Factory, Jogana',
                'directed_put_away_and_pick' => false,
                'bin_mandatory' => true,
                'require_receive' => false,
                'require_shipment' => false,
                'require_put_away' => false,
                'require_pick' => false,
                'receipt_bin_code' => 'SCRAP-RCV',
                'shipment_bin_code' => null,
                'open_shop_floor_bin_code' => null,
                'inbound_production_bin_code' => 'SCRAP-IN',
                'outbound_production_bin_code' => 'SCRAP-OUT',
                'adjustment_bin_code' => 'SCRAP-ADJ',
                'blocked' => false,
            ],

        ];

        $createdCount = 0;
        $existingCount = 0;

        foreach ($locations as $locationData) {
            $location = Location::query()->firstOrCreate(
                ['code' => $locationData['code']],
                $locationData,
            );

            if ($location->wasRecentlyCreated) {
                $createdCount++;
            } else {
                $existingCount++;
            }
        }

        $this->command?->info('Locations seeded successfully.');
        $this->command?->info('Configured: '.count($locations));
        $this->command?->info('Created: '.$createdCount);
        $this->command?->info('Already existing: '.$existingCount);
        $this->command?->info(
            'Active: '.collect($locations)->where('blocked', false)->count(),
        );
        $this->command?->info(
            'Blocked: '.collect($locations)->where('blocked', true)->count(),
        );
        $this->command?->info(
            'Full WMS: '
            .collect($locations)
                ->where('directed_put_away_and_pick', true)
                ->count(),
        );
        $this->command?->info(
            'Basic Bin: '
            .collect($locations)
                ->where('bin_mandatory', true)
                ->where('directed_put_away_and_pick', false)
                ->count(),
        );
        $this->command?->info(
            'No Bin Control: '
            .collect($locations)
                ->where('bin_mandatory', false)
                ->count(),
        );
    }
}
