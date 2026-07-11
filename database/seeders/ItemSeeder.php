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
use App\Models\VatProductPostingGroup;
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
        $rawMatGroup = GeneralProductPostingGroup::where('code', 'RAW')->first()
            ?? GeneralProductPostingGroup::create(['code' => 'RAW', 'description' => 'Raw Materials']);
        $packagingGroup = GeneralProductPostingGroup::where('code', 'PACKAGING')->first()
            ?? GeneralProductPostingGroup::create(['code' => 'PACKAGING', 'description' => 'Packaging']);

        $finishedInvGroup = InventoryPostingGroup::where('code', 'FINISHED')->first()
            ?? InventoryPostingGroup::create(['code' => 'FINISHED', 'description' => 'Finished Goods']);

        $rawMatInvGroup = InventoryPostingGroup::where('code', 'RAW')->first()
            ?? InventoryPostingGroup::create(['code' => 'RAW', 'description' => 'Raw Material']);

        $packagingInvGroup = InventoryPostingGroup::where('code', 'PACKAGING')->first()
            ?? InventoryPostingGroup::create(['code' => 'PACKAGING', 'description' => 'Packaging Material']);

        $standardVatProdGroup = VatProductPostingGroup::where('code', 'STANDARD')->first();
        $zeroVatProdGroup = VatProductPostingGroup::where('code', 'ZERO')->first();

        // Cache some common lookups
        $vats = VatMaster::all()->pluck('id', 'code');
        $baseUomId = UnitOfMeasure::firstOrCreate(
            ['uom_code' => 'PCS'],
            ['description' => 'Pieces']
        )->id;

        $uomId = UnitOfMeasure::firstOrCreate(
            ['uom_code' => 'G'],
            ['description' => 'Gram']
        )->id;

        $items = [
            [
                'item_code' => '1000',
                'description' => 'Mai sasanci',
                'description_2' => 'Mai sasanci 60ml',
                'general_product_posting_group_id' => $retailGroup->id,
                'inventory_posting_group_id' => $finishedInvGroup->id,
                'vat_prod_posting_group' => 'VAT20',
                'item_type' => ItemType::FINISHED_GOOD->value,
                'costing_method' => CostingMethod::FIFO->value,
                'inventory_method' => InventoryMethod::FIFO,
                'unit_cost' => 850.00,
                'standard_cost' => 850.00,
                'last_direct_cost' => 845.00,
                'unit_price' => 1200.00,
                'inventory' => 50.00,
                'reorder_point' => 10.00,
                'reorder_quantity' => 25.00,
                'location_id' => $mainLocation->id,
                'bin_code' => 'A-01-01',
                'base_uom_id' => $baseUomId,
                'weight' => 2.50,
                'blocked' => false,
            ],
            [
                'item_code' => '2100',
                'description' => 'Sodium Saccharine',
                'description_2' => 'Sodium Saccharine',
                'general_product_posting_group_id' => $retailGroup->id,
                'inventory_posting_group_id' => $rawMatInvGroup->id,
                'vat_prod_posting_group' => 'VAT20',
                'item_type' => ItemType::RAW_MATERIAL->value,
                'costing_method' => CostingMethod::FIFO->value,
                'inventory_method' => InventoryMethod::FIFO,
                'unit_cost' => 8.80,
                'standard_cost' => 8.80,
                'last_direct_cost' => 8.80,
                'unit_price' => 8.80,
                'inventory' => 25000.00,
                'reorder_point' => 250000.00,
                'reorder_quantity' => 250000.00,
                'location_id' => $mainLocation->id,
                'bin_code' => 'B-02-15',
                'base_uom_id' => $uomId,
                'weight' => 0.00,
                'blocked' => false,
            ],
            [
                'item_code' => '2200',
                'description' => 'Ginseng',
                'description_2' => 'Ginseng',
                'general_product_posting_group_id' => $rawMatGroup->id,
                'inventory_posting_group_id' => $rawMatInvGroup->id,
                'vat_prod_posting_group' => 'VAT20',
                'item_type' => ItemType::RAW_MATERIAL->value,
                'costing_method' => CostingMethod::STANDARD->value,
                'inventory_method' => InventoryMethod::FIFO,
                'unit_cost' => 5000.00,
                'standard_cost' => 5000.00,
                'last_direct_cost' => 0.00,
                'unit_price' => 5000.00,
                'inventory' => 400000.00,
                'reorder_point' => 250000.00,
                'reorder_quantity' => 0.00,
                'base_uom_id' => $uomId,
                'blocked' => false,
            ],
            [
                'item_code' => '2300',
                'description' => 'Yohimbine',
                'description_2' => 'Yohimbine',
                'general_product_posting_group_id' => $rawMatGroup->id,
                'inventory_posting_group_id' => $rawMatInvGroup->id,
                'vat_prod_posting_group' => 'VAT20',
                'item_type' => ItemType::RAW_MATERIAL->value,
                'costing_method' => CostingMethod::STANDARD->value,
                'inventory_method' => InventoryMethod::FIFO,
                'unit_cost' => 5000.00,
                'standard_cost' => 5000.00,
                'last_direct_cost' => 0.00,
                'unit_price' => 5000.00,
                'inventory' => 1000000.00,
                'reorder_point' => 500000.00,
                'reorder_quantity' => 0.00,
                'base_uom_id' => $uomId,
                'blocked' => false,
            ],
            [
                'item_code' => '2400',
                'description' => 'Sodium Benzoate',
                'description_2' => 'Sodium Benzoate',
                'general_product_posting_group_id' => $rawMatGroup->id,
                'inventory_posting_group_id' => $rawMatInvGroup->id,
                'vat_prod_posting_group' => 'VAT20',
                'item_type' => ItemType::RAW_MATERIAL->value,
                'costing_method' => CostingMethod::STANDARD->value,
                'inventory_method' => InventoryMethod::FIFO,
                'unit_cost' => 20.00,
                'standard_cost' => 20.00,
                'last_direct_cost' => 0.00,
                'unit_price' => 20.00,
                'inventory' => 10000.00,
                'reorder_point' => 0.00,
                'reorder_quantity' => 0.00,
                'base_uom_id' => $uomId,
                'blocked' => false,
            ],
            [
                'item_code' => '2500',
                'description' => 'Rubber & Cap',
                'description_2' => 'Mai sasanci 60ml Rubber & Cap',
                'general_product_posting_group_id' => $packagingGroup->id,
                'inventory_posting_group_id' => $packagingInvGroup->id,
                'vat_prod_posting_group' => 'VAT20',
                'item_type' => ItemType::PACKAGING->value,
                'costing_method' => CostingMethod::STANDARD->value,
                'inventory_method' => InventoryMethod::FIFO,
                'unit_cost' => 47.43,
                'standard_cost' => 47.43,
                'last_direct_cost' => 47.43,
                'unit_price' => 47.43,
                'inventory' => 10000.00,
                'reorder_point' => 0.00,
                'reorder_quantity' => 0.00,
                'base_uom_id' => $uomId,
                'blocked' => false,
            ],
            [
                'item_code' => '2600',
                'description' => 'Label',
                'description_2' => 'Mai sasanci 60ml Label',
                'general_product_posting_group_id' => $packagingGroup->id,
                'inventory_posting_group_id' => $packagingInvGroup->id,
                'vat_prod_posting_group' => 'VAT20',
                'item_type' => ItemType::PACKAGING->value,
                'costing_method' => CostingMethod::STANDARD->value,
                'inventory_method' => InventoryMethod::FIFO,
                'unit_cost' => 20.00,
                'standard_cost' => 20.00,
                'last_direct_cost' => 0.00,
                'unit_price' => 20.00,
                'inventory' => 10000.00,
                'reorder_point' => 0.00,
                'reorder_quantity' => 0.00,
                'base_uom_id' => $baseUomId,
                'blocked' => false,
            ],
            [
                'item_code' => '2700',
                'description' => 'Shrink Sleeve',
                'description_2' => 'Mai sasanci 60ml Shrink Sleeve',
                'general_product_posting_group_id' => $packagingGroup->id,
                'inventory_posting_group_id' => $packagingInvGroup->id,
                'vat_prod_posting_group' => 'VAT20',
                'item_type' => ItemType::PACKAGING->value,
                'costing_method' => CostingMethod::STANDARD->value,
                'inventory_method' => InventoryMethod::FIFO,
                'unit_cost' => 150.00,
                'standard_cost' => 150.00,
                'last_direct_cost' => 0.00,
                'unit_price' => 150.00,
                'inventory' => 1000.00,
                'reorder_point' => 0.00,
                'reorder_quantity' => 0.00,
                'base_uom_id' => $baseUomId,
                'blocked' => false,
            ],
            [
                'item_code' => '2800',
                'description' => 'Paper Tray',
                'description_2' => 'Mai sasanci 60ml Paper Tray',
                'general_product_posting_group_id' => $packagingGroup->id,
                'inventory_posting_group_id' => $packagingInvGroup->id,
                'vat_prod_posting_group' => 'VAT20',
                'item_type' => ItemType::PACKAGING->value,
                'costing_method' => CostingMethod::STANDARD->value,
                'inventory_method' => InventoryMethod::FIFO,
                'unit_cost' => 150.00,
                'standard_cost' => 150.00,
                'last_direct_cost' => 0.00,
                'unit_price' => 150.00,
                'inventory' => 1000.00,
                'reorder_point' => 0.00,
                'reorder_quantity' => 0.00,
                'base_uom_id' => $baseUomId,
                'blocked' => false,
            ],
            [
                'item_code' => '2900',
                'description' => 'Carton',
                'description_2' => 'Mai sasanci 60ml Carton Box',
                'general_product_posting_group_id' => $packagingGroup->id,
                'inventory_posting_group_id' => $packagingInvGroup->id,
                'vat_prod_posting_group' => 'VAT20',
                'item_type' => ItemType::PACKAGING->value,
                'costing_method' => CostingMethod::STANDARD->value,
                'inventory_method' => InventoryMethod::STANDARD->value,
                'unit_cost' => 267.00,
                'standard_cost' => 267.00,
                'last_direct_cost' => 0.00,
                'unit_price' => 267.00,
                'inventory' => 100.00,
                'reorder_point' => 0.00,
                'reorder_quantity' => 0.00,
                'base_uom_id' => $baseUomId,
                'blocked' => false,
            ],
        ];

        foreach ($items as $itemData) {
            // Resolve VAT ID
            $itemData['vat_id'] = $vats[$itemData['vat_prod_posting_group']] ?? null;

            // Resolve Posting Setups (Best effort)
            $itemData['general_posting_setup_id'] = GeneralPostingSetup::where('general_product_posting_group_id', $itemData['general_product_posting_group_id'])->first()?->id;

            // Map VAT product group
            $itemData['vat_product_posting_group_id'] = $itemData['vat_prod_posting_group'] === 'VAT20'
                ? $standardVatProdGroup?->id
                : $zeroVatProdGroup?->id;

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
