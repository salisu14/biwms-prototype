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
        ];

        foreach ($locations as $location) {
            Location::firstOrCreate(
                ['code' => $location['code']],
                $location
            );
        }

        $this->command->info('Locations seeded successfully!');
        $this->command->info('Total: '.count($locations).' locations');
        $this->command->info('Active: '.collect($locations)->where('blocked', false)->count());
        $this->command->info('Blocked: '.collect($locations)->where('blocked', true)->count());
        $this->command->info('Full WMS: '.collect($locations)->where('directed_put_away_and_pick', true)->count());
        $this->command->info('Basic Bin: '.collect($locations)->where('bin_mandatory', true)->where('directed_put_away_and_pick', false)->count());
        $this->command->info('No Bin Control: '.collect($locations)->where('bin_mandatory', false)->count());
    }
}
