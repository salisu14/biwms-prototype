<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\Location;
use App\Models\GeneralProductPostingGroup;
use App\Models\InventoryPostingGroup;
use App\Enums\ItemType;
use App\Enums\CostingMethod;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure we have supporting data
        $mainLocation = Location::first() ?? Location::create(['name' => 'Main Warehouse', 'code' => 'MAIN']);

        $retailGroup = GeneralProductPostingGroup::where('code', 'RETAIL')->first()
            ?? GeneralProductPostingGroup::create(['code' => 'RETAIL', 'description' => 'Retail Items']);

        $serviceGroup = GeneralProductPostingGroup::where('code', 'SERVICE')->first()
            ?? GeneralProductPostingGroup::create(['code' => 'SERVICE', 'description' => 'Service Items']);

        // Aligning with InventoryPostingGroupSeeder codes
        $finishedInvGroup = InventoryPostingGroup::where('code', 'FINISHED')->first()
            ?? InventoryPostingGroup::create(['code' => 'FINISHED', 'description' => 'Finished Goods']);

        // Creating a fallback group for services since the column is NOT NULL
        $serviceInvGroup = InventoryPostingGroup::where('code', 'SERVICE')->first()
            ?? InventoryPostingGroup::create(['code' => 'SERVICE', 'description' => 'Service Posting Group']);

        $items = [
            [
                'item_number' => '1000',
                'description' => 'High-Performance Laptop Z1',
                'description_2' => '16GB RAM, 512GB SSD',
                'general_product_posting_group_id' => $retailGroup->id,
                'inventory_posting_group_id' => $finishedInvGroup->id,
                'vat_prod_posting_group' => 'VAT20',
                'item_type' => ItemType::FINISHED_GOOD->value,
                'costing_method' => CostingMethod::FIFO->value,
                'unit_cost' => 850.0000,
                'standard_cost' => 850.0000,
                'last_direct_cost' => 845.0000,
                'unit_price' => 1200.0000,
                'inventory' => 50.0000,
                'reorder_point' => 10.0000,
                'reorder_quantity' => 25.0000,
                'location_id' => $mainLocation->id,
                'bin_code' => 'A-01-01',
                'base_unit_of_measure' => 'PCS',
                'weight' => 2.5000,
                'blocked' => false,
            ],
            [
                'item_number' => '1100',
                'description' => 'Wireless Ergonomic Mouse',
                'description_2' => 'Bluetooth 5.0, Rechargeable',
                'general_product_posting_group_id' => $retailGroup->id,
                'inventory_posting_group_id' => $finishedInvGroup->id,
                'vat_prod_posting_group' => 'VAT20',
                'item_type' => ItemType::FINISHED_GOOD->value,
                'costing_method' => CostingMethod::AVERAGE->value,
                'unit_cost' => 25.5000,
                'standard_cost' => 25.0000,
                'last_direct_cost' => 26.0000,
                'unit_price' => 55.0000,
                'inventory' => 200.0000,
                'reorder_point' => 50.0000,
                'reorder_quantity' => 100.0000,
                'location_id' => $mainLocation->id,
                'bin_code' => 'B-02-15',
                'base_unit_of_measure' => 'PCS',
                'weight' => 0.1500,
                'blocked' => false,
            ],
            [
                'item_number' => 'SERV-01',
                'description' => 'On-Site Technical Support',
                'description_2' => 'Hourly rate for hardware repair',
                'general_product_posting_group_id' => $serviceGroup->id,
                'inventory_posting_group_id' => $serviceInvGroup->id, // Fixed: Cannot be null due to DB constraint
                'vat_prod_posting_group' => 'VAT20',
                'item_type' => ItemType::SERVICE->value,
                'costing_method' => CostingMethod::STANDARD->value,
                'unit_cost' => 45.0000,
                'standard_cost' => 45.0000,
                'last_direct_cost' => 0.0000,
                'unit_price' => 110.0000,
                'inventory' => 0.0000,
                'reorder_point' => 0.0000,
                'reorder_quantity' => 0.0000,
                'base_unit_of_measure' => 'HOUR',
                'blocked' => false,
            ],
            [
                'item_number' => '9000',
                'description' => 'Discontinued Monitor X2',
                'description_2' => 'Legacy Model 24-inch',
                'general_product_posting_group_id' => $retailGroup->id,
                'inventory_posting_group_id' => $finishedInvGroup->id,
                'vat_prod_posting_group' => 'VAT20',
                'item_type' => ItemType::FINISHED_GOOD->value,
                'costing_method' => CostingMethod::FIFO->value,
                'unit_cost' => 110.0000,
                'unit_price' => 199.0000,
                'inventory' => 5.0000,
                'location_id' => $mainLocation->id,
                'base_unit_of_measure' => 'PCS',
                'blocked' => true,
                'sales_blocked' => true,
                'purchasing_blocked' => true,
            ],
        ];

        foreach ($items as $itemData) {
            Item::updateOrCreate(
                ['item_number' => $itemData['item_number']],
                $itemData
            );
        }
    }
}
