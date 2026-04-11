<?php

namespace Database\Seeders;

use App\Enums\CostingMethod;
use App\Enums\InventoryMethod;
use App\Enums\ItemType;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemSku;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\VatMaster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Item::truncate();
        ItemSku::truncate();
        Schema::enableForeignKeyConstraints();

        // Ensure we have supporting data
        $mainLocation = Location::first() ?? Location::create(['name' => 'Main Warehouse', 'code' => 'MAIN']);

        $retailGroup = GeneralProductPostingGroup::where('code', 'RETAIL')->first()
            ?? GeneralProductPostingGroup::create(['code' => 'RETAIL', 'description' => 'Retail Items']);

        $serviceGroup = GeneralProductPostingGroup::where('code', 'SERVICE')->first()
            ?? GeneralProductPostingGroup::create(['code' => 'SERVICE', 'description' => 'Service Items']);

        $finishedInvGroup = InventoryPostingGroup::where('code', 'FINISHED')->first()
            ?? InventoryPostingGroup::create(['code' => 'FINISHED', 'description' => 'Finished Goods']);

        $serviceInvGroup = InventoryPostingGroup::where('code', 'SERVICE')->first()
            ?? InventoryPostingGroup::create(['code' => 'SERVICE', 'description' => 'Service Posting Group']);

        // Cache some common lookups
        $vats = VatMaster::all()->pluck('id', 'code');
        $uoms = UnitOfMeasure::all()->pluck('id', 'uom_code');

        $items = [
            [
                'item_code' => '1000',
                'description' => 'High-Performance Laptop Z1',
                'description_2' => '16GB RAM, 512GB SSD',
                'general_product_posting_group_id' => $retailGroup->id,
                'inventory_posting_group_id' => $finishedInvGroup->id,
                'vat_prod_posting_group' => 'VAT20',
                'item_type' => ItemType::FINISHED_GOOD->value,
                'costing_method' => CostingMethod::FIFO->value,
                'inventory_method' => InventoryMethod::FIFO,
                'unit_cost' => 850.0000,
                'standard_cost' => 850.0000,
                'last_direct_cost' => 845.0000,
                'unit_price' => 1200.0000,
                'inventory' => 50.0000,
                'reorder_point' => 10.0000,
                'reorder_quantity' => 25.0000,
                'location_id' => $mainLocation->id,
                'bin_code' => 'A-01-01',
                'base_unit_of_measure' => 'EA',
                'weight' => 2.5000,
                'blocked' => false,
            ],
            [
                'item_code' => '1100',
                'description' => 'Wireless Ergonomic Mouse',
                'description_2' => 'Bluetooth 5.0, Rechargeable',
                'general_product_posting_group_id' => $retailGroup->id,
                'inventory_posting_group_id' => $finishedInvGroup->id,
                'vat_prod_posting_group' => 'VAT20',
                'item_type' => ItemType::FINISHED_GOOD->value,
                'costing_method' => CostingMethod::AVERAGE->value,
                'inventory_method' => InventoryMethod::AVERAGE,
                'unit_cost' => 25.5000,
                'standard_cost' => 25.0000,
                'last_direct_cost' => 26.0000,
                'unit_price' => 55.0000,
                'inventory' => 200.0000,
                'reorder_point' => 50.0000,
                'reorder_quantity' => 100.0000,
                'location_id' => $mainLocation->id,
                'bin_code' => 'B-02-15',
                'base_unit_of_measure' => 'EA',
                'weight' => 0.1500,
                'blocked' => false,
            ],
            [
                'item_code' => 'SERV-01',
                'description' => 'On-Site Technical Support',
                'description_2' => 'Hourly rate for hardware repair',
                'general_product_posting_group_id' => $serviceGroup->id,
                'inventory_posting_group_id' => $serviceInvGroup->id,
                'vat_prod_posting_group' => 'VAT20',
                'item_type' => ItemType::SERVICE->value,
                'costing_method' => CostingMethod::STANDARD->value,
                'inventory_method' => InventoryMethod::FIFO,
                'unit_cost' => 45.0000,
                'standard_cost' => 45.0000,
                'last_direct_cost' => 0.0000,
                'unit_price' => 110.0000,
                'inventory' => 0.0000,
                'reorder_point' => 0.0000,
                'reorder_quantity' => 0.0000,
                'base_unit_of_measure' => 'EA',
                'blocked' => false,
            ],
        ];

        foreach ($items as $itemData) {
            // Resolve UOM ID
            $itemData['uom_id'] = $uoms[$itemData['base_unit_of_measure']] ?? null;

            // Resolve VAT ID
            $itemData['vat_id'] = $vats[$itemData['vat_prod_posting_group']] ?? null;

            // Resolve Posting Setups (Best effort)
            $itemData['general_posting_setup_id'] = GeneralPostingSetup::where('general_product_posting_group_id', $itemData['general_product_posting_group_id'])->first()?->id;
            $itemData['inventory_posting_setup_id'] = InventoryPostingSetup::where([
                'inventory_posting_group_id' => $itemData['inventory_posting_group_id'],
                'location_id' => $itemData['location_id'] ?? $mainLocation->id,
            ])->first()?->id;

            $item = Item::updateOrCreate(
                ['item_code' => $itemData['item_code']],
                $itemData
            );

            // Create/Sync SKU for this item at main location
            if ($item->location_id) {
                $sku = ItemSku::updateOrCreate([
                    'item_id' => $item->id,
                    'location_id' => $item->location_id,
                ], [
                    'sku_code' => $item->item_code.'-'.$mainLocation->code,
                    'is_active' => true,
                ]);

                $item->update(['sku_id' => $sku->id]);
            }
        }
    }
}
