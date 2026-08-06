<?php

declare(strict_types=1);

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
use RuntimeException;

class ItemSeeder extends Seeder
{
    /**
     * Seed the real BIWMS item master records.
     *
     * Important:
     * - This seeder does not establish inventory quantities.
     * - New items start with stock = 0.
     * - Existing item stock is never reset when this seeder is rerun.
     * - Actual physical stock must be posted through Opening Inventory.
     */
    public function run(): void
    {
        /*
         * Required locations.
         *
         * LocationSeeder must run before ItemSeeder.
         */
        $rawMaterialLocation = $this->requireLocation('GBS-RAWMAT');
        $packagingLocation = $this->requireLocation('GBS-PACK');
        $finishedGoodsLocation = $this->requireLocation('GBS-FGN');

        /*
         * Required General Product Posting Groups.
         *
         * GeneralProductPostingGroupSeeder must run before ItemSeeder.
         */
        $rawMaterialProductGroup = $this->requireGeneralProductPostingGroup(
            'RAWMAT',
        );

        $retailProductGroup = $this->requireGeneralProductPostingGroup(
            'RETAIL',
        );

        $packagingProductGroup = $this->requireGeneralProductPostingGroup(
            'PACKAGING',
        );

        /*
         * Required Inventory Posting Groups.
         *
         * InventoryPostingGroupSeeder must run before ItemSeeder.
         */
        $rawMaterialInventoryGroup = $this->requireInventoryPostingGroup(
            'RAW',
        );

        $finishedGoodsInventoryGroup = $this->requireInventoryPostingGroup(
            'FINISHED',
        );

        $packagingInventoryGroup = $this->requireInventoryPostingGroup(
            'PACKAGING',
        );

        /*
         * Required VAT Product Posting Groups.
         */
        $standardVatProductGroup = VatProductPostingGroup::query()
            ->where('code', 'STANDARD')
            ->firstOrFail();

        /*
         * VAT20 is retained as the existing VAT Master code used by the
         * item records. Change this code only if VatMasterSeeder uses a
         * different code in the current project.
         */
        $standardVat = VatMaster::query()
            ->where('code', 'VAT20')
            ->first();

        /*
         * Required Units of Measure.
         */
        $piecesUom = UnitOfMeasure::query()->firstOrCreate(
            ['uom_code' => 'PCS'],
            ['description' => 'Pieces'],
        );

        $gramUom = UnitOfMeasure::query()->firstOrCreate(
            ['uom_code' => 'G'],
            ['description' => 'Gram'],
        );

        $items = [
            [
                'item_code' => '1000',
                'description' => 'Mai Sasanci',
                'description_2' => 'Mai Sasanci 60ml',
                'general_product_posting_group_id' => $retailProductGroup->id,
                'inventory_posting_group_id' => $finishedGoodsInventoryGroup->id,
                'vat_id' => $standardVat?->id,
                'vat_product_posting_group_id' => $standardVatProductGroup->id,
                'item_type' => ItemType::FINISHED_GOOD->value,
                'costing_method' => CostingMethod::FIFO->value,
                'inventory_method' => InventoryMethod::FIFO->value,
                'unit_cost' => 850.00,
                'standard_cost' => 850.00,
                'last_direct_cost' => 845.00,
                'unit_price' => 1200.00,
                'reorder_point' => 10.00,
                'reorder_quantity' => 25.00,
                'location_id' => $finishedGoodsLocation->id,
                'bin_code' => 'A-01-01',
                'base_uom_id' => $piecesUom->id,
                'weight' => 2.50,
                'blocked' => false,
            ],
            [
                'item_code' => '2100',
                'description' => 'Sodium Saccharine',
                'description_2' => 'Sodium Saccharine',
                'general_product_posting_group_id' => $rawMaterialProductGroup->id,
                'inventory_posting_group_id' => $rawMaterialInventoryGroup->id,
                'vat_id' => $standardVat?->id,
                'vat_product_posting_group_id' => $standardVatProductGroup->id,
                'item_type' => ItemType::RAW_MATERIAL->value,
                'costing_method' => CostingMethod::FIFO->value,
                'inventory_method' => InventoryMethod::FIFO->value,
                'unit_cost' => 8.80,
                'standard_cost' => 8.80,
                'last_direct_cost' => 8.80,
                'unit_price' => 8.80,
                'reorder_point' => 250000.00,
                'reorder_quantity' => 250000.00,
                'location_id' => $rawMaterialLocation->id,
                'bin_code' => 'B-02-15',
                'base_uom_id' => $gramUom->id,
                'weight' => 0.00,
                'blocked' => false,
            ],
            [
                'item_code' => '2200',
                'description' => 'Ginseng',
                'description_2' => 'Ginseng',
                'general_product_posting_group_id' => $rawMaterialProductGroup->id,
                'inventory_posting_group_id' => $rawMaterialInventoryGroup->id,
                'vat_id' => $standardVat?->id,
                'vat_product_posting_group_id' => $standardVatProductGroup->id,
                'item_type' => ItemType::RAW_MATERIAL->value,
                'costing_method' => CostingMethod::STANDARD->value,
                'inventory_method' => InventoryMethod::FIFO->value,
                'unit_cost' => 778.50,
                'standard_cost' => 778.50,
                'last_direct_cost' => 0.00,
                'unit_price' => 778.50,
                'reorder_point' => 250000.00,
                'reorder_quantity' => 0.00,
                'location_id' => $rawMaterialLocation->id,
                'base_uom_id' => $gramUom->id,
                'blocked' => false,
            ],
            [
                'item_code' => '2300',
                'description' => 'Yohimbine',
                'description_2' => 'Yohimbine',
                'general_product_posting_group_id' => $rawMaterialProductGroup->id,
                'inventory_posting_group_id' => $rawMaterialInventoryGroup->id,
                'vat_id' => $standardVat?->id,
                'vat_product_posting_group_id' => $standardVatProductGroup->id,
                'item_type' => ItemType::RAW_MATERIAL->value,
                'costing_method' => CostingMethod::STANDARD->value,
                'inventory_method' => InventoryMethod::FIFO->value,
                'unit_cost' => 336.00,
                'standard_cost' => 336.00,
                'last_direct_cost' => 0.00,
                'unit_price' => 336.00,
                'reorder_point' => 500000.00,
                'reorder_quantity' => 0.00,
                'location_id' => $rawMaterialLocation->id,
                'base_uom_id' => $gramUom->id,
                'blocked' => false,
            ],
            [
                'item_code' => '2400',
                'description' => 'Sodium Benzoate',
                'description_2' => 'Sodium Benzoate',
                'general_product_posting_group_id' => $rawMaterialProductGroup->id,
                'inventory_posting_group_id' => $rawMaterialInventoryGroup->id,
                'vat_id' => $standardVat?->id,
                'vat_product_posting_group_id' => $standardVatProductGroup->id,
                'item_type' => ItemType::RAW_MATERIAL->value,
                'costing_method' => CostingMethod::STANDARD->value,
                'inventory_method' => InventoryMethod::FIFO->value,
                'unit_cost' => 2.80,
                'standard_cost' => 2.80,
                'last_direct_cost' => 0.00,
                'unit_price' => 2.80,
                'reorder_point' => 0.00,
                'reorder_quantity' => 0.00,
                'location_id' => $rawMaterialLocation->id,
                'base_uom_id' => $gramUom->id,
                'blocked' => false,
            ],
            [
                'item_code' => '2410',
                'description' => 'Ficus Carica',
                'description_2' => 'Ficus Carica',
                'general_product_posting_group_id' => $rawMaterialProductGroup->id,
                'inventory_posting_group_id' => $rawMaterialInventoryGroup->id,
                'vat_id' => $standardVat?->id,
                'vat_product_posting_group_id' => $standardVatProductGroup->id,
                'item_type' => ItemType::RAW_MATERIAL->value,
                'costing_method' => CostingMethod::STANDARD->value,
                'inventory_method' => InventoryMethod::FIFO->value,
                'unit_cost' => 20000.00,
                'standard_cost' => 20000.00,
                'last_direct_cost' => 0.00,
                'unit_price' => 20000.00,
                'reorder_point' => 0.00,
                'reorder_quantity' => 0.00,
                'location_id' => $rawMaterialLocation->id,
                'base_uom_id' => $gramUom->id,
                'blocked' => false,
            ],
            [
                'item_code' => '2500',
                'description' => 'Rubber and Cap',
                'description_2' => 'Mai Sasanci 60ml Rubber and Cap',
                'general_product_posting_group_id' => $packagingProductGroup->id,
                'inventory_posting_group_id' => $packagingInventoryGroup->id,
                'vat_id' => $standardVat?->id,
                'vat_product_posting_group_id' => $standardVatProductGroup->id,
                'item_type' => ItemType::PACKAGING->value,
                'costing_method' => CostingMethod::STANDARD->value,
                'inventory_method' => InventoryMethod::FIFO->value,
                'unit_cost' => 47.43,
                'standard_cost' => 47.43,
                'last_direct_cost' => 47.43,
                'unit_price' => 47.43,
                'reorder_point' => 0.00,
                'reorder_quantity' => 0.00,
                'location_id' => $packagingLocation->id,
                'base_uom_id' => $piecesUom->id,
                'blocked' => false,
            ],
            [
                'item_code' => '2600',
                'description' => 'Label',
                'description_2' => 'Mai Sasanci 60ml Label',
                'general_product_posting_group_id' => $packagingProductGroup->id,
                'inventory_posting_group_id' => $packagingInventoryGroup->id,
                'vat_id' => $standardVat?->id,
                'vat_product_posting_group_id' => $standardVatProductGroup->id,
                'item_type' => ItemType::PACKAGING->value,
                'costing_method' => CostingMethod::STANDARD->value,
                'inventory_method' => InventoryMethod::FIFO->value,
                'unit_cost' => 20.00,
                'standard_cost' => 20.00,
                'last_direct_cost' => 0.00,
                'unit_price' => 20.00,
                'reorder_point' => 0.00,
                'reorder_quantity' => 0.00,
                'location_id' => $packagingLocation->id,
                'base_uom_id' => $piecesUom->id,
                'blocked' => false,
            ],
            [
                'item_code' => '2700',
                'description' => 'Shrink Sleeve',
                'description_2' => 'Mai Sasanci 60ml Shrink Sleeve',
                'general_product_posting_group_id' => $packagingProductGroup->id,
                'inventory_posting_group_id' => $packagingInventoryGroup->id,
                'vat_id' => $standardVat?->id,
                'vat_product_posting_group_id' => $standardVatProductGroup->id,
                'item_type' => ItemType::PACKAGING->value,
                'costing_method' => CostingMethod::STANDARD->value,
                'inventory_method' => InventoryMethod::FIFO->value,
                'unit_cost' => 150.00,
                'standard_cost' => 150.00,
                'last_direct_cost' => 0.00,
                'unit_price' => 150.00,
                'reorder_point' => 0.00,
                'reorder_quantity' => 0.00,
                'location_id' => $packagingLocation->id,
                'base_uom_id' => $piecesUom->id,
                'blocked' => false,
            ],
            [
                'item_code' => '2800',
                'description' => 'Paper Tray 12x60ml',
                'description_2' => 'Mai Sasanci 12x60ml Paper Tray',
                'general_product_posting_group_id' => $packagingProductGroup->id,
                'inventory_posting_group_id' => $packagingInventoryGroup->id,
                'vat_id' => $standardVat?->id,
                'vat_product_posting_group_id' => $standardVatProductGroup->id,
                'item_type' => ItemType::PACKAGING->value,
                'costing_method' => CostingMethod::STANDARD->value,
                'inventory_method' => InventoryMethod::FIFO->value,
                'unit_cost' => 70.14,
                'standard_cost' => 70.14,
                'last_direct_cost' => 0.00,
                'unit_price' => 70.14,
                'reorder_point' => 0.00,
                'reorder_quantity' => 0.00,
                'location_id' => $packagingLocation->id,
                'base_uom_id' => $piecesUom->id,
                'blocked' => false,
            ],
            [
                'item_code' => '2900',
                'description' => 'Mai Sasanci 3-Ply Box',
                'description_2' => 'Mai Sasanci 3-Ply Box',
                'general_product_posting_group_id' => $packagingProductGroup->id,
                'inventory_posting_group_id' => $packagingInventoryGroup->id,
                'vat_id' => $standardVat?->id,
                'vat_product_posting_group_id' => $standardVatProductGroup->id,
                'item_type' => ItemType::PACKAGING->value,
                'costing_method' => CostingMethod::STANDARD->value,
                'inventory_method' => InventoryMethod::STANDARD->value,
                'unit_cost' => 723.00,
                'standard_cost' => 723.00,
                'last_direct_cost' => 0.00,
                'unit_price' => 723.00,
                'reorder_point' => 0.00,
                'reorder_quantity' => 0.00,
                'location_id' => $packagingLocation->id,
                'base_uom_id' => $piecesUom->id,
                'blocked' => false,
            ],
            [
                'item_code' => '3000',
                'description' => 'PVC Shrink Plain 12x60ml',
                'description_2' => 'Mai Sasanci 12x60ml Plain PVC Shrink',
                'general_product_posting_group_id' => $packagingProductGroup->id,
                'inventory_posting_group_id' => $packagingInventoryGroup->id,
                'vat_id' => $standardVat?->id,
                'vat_product_posting_group_id' => $standardVatProductGroup->id,
                'item_type' => ItemType::PACKAGING->value,
                'costing_method' => CostingMethod::STANDARD->value,
                'inventory_method' => InventoryMethod::STANDARD->value,
                'unit_cost' => 35.38,
                'standard_cost' => 35.38,
                'last_direct_cost' => 0.00,
                'unit_price' => 35.38,
                'reorder_point' => 0.00,
                'reorder_quantity' => 0.00,
                'location_id' => $packagingLocation->id,
                'base_uom_id' => $piecesUom->id,
                'blocked' => false,
            ],
            [
                'item_code' => '3100',
                'description' => 'PVC Shrink Label Mai Sasanci 60ml',
                'description_2' => 'PVC Shrink Label Mai Sasanci 60ml',
                'general_product_posting_group_id' => $packagingProductGroup->id,
                'inventory_posting_group_id' => $packagingInventoryGroup->id,
                'vat_id' => $standardVat?->id,
                'vat_product_posting_group_id' => $standardVatProductGroup->id,
                'item_type' => ItemType::PACKAGING->value,
                'costing_method' => CostingMethod::STANDARD->value,
                'inventory_method' => InventoryMethod::STANDARD->value,
                'unit_cost' => 7.27,
                'standard_cost' => 7.27,
                'last_direct_cost' => 0.00,
                'unit_price' => 35.38,
                'reorder_point' => 0.00,
                'reorder_quantity' => 0.00,
                'location_id' => $packagingLocation->id,
                'base_uom_id' => $piecesUom->id,
                'blocked' => false,
            ],
        ];

        $createdCount = 0;
        $updatedCount = 0;

        foreach ($items as $itemData) {
            $itemLocationId = (int) $itemData['location_id'];

            /*
             * Resolve the location-specific inventory posting setup.
             *
             * This is deliberately required. Opening Inventory and normal
             * inventory posting must not silently proceed without a valid
             * inventory posting account configuration.
             */
            $itemData['inventory_posting_setup_id'] =
                $this->resolveInventoryPostingSetupId(
                    inventoryPostingGroupId: (int) $itemData[
                    'inventory_posting_group_id'
                    ],
                    locationId: $itemLocationId,
                    itemCode: (string) $itemData['item_code'],
                );

            /*
             * General Posting Setup can contain multiple rows for one Product
             * Posting Group because it is normally also scoped by a General
             * Business Posting Group.
             *
             * This helper accepts one unambiguous record. If multiple records
             * exist, it returns null rather than selecting an arbitrary row.
             * Document posting services should resolve the complete business
             * and product group combination from the transaction context.
             */
            $itemData['general_posting_setup_id'] =
                $this->resolveUnambiguousGeneralPostingSetupId(
                    (int) $itemData['general_product_posting_group_id'],
                );

            $item = Item::query()->firstOrNew([
                'item_code' => $itemData['item_code'],
            ]);

            $isNewItem = ! $item->exists;

            /*
             * Do not pass inventory through fill(). It is intentionally absent
             * from the item data so a later seeder run cannot reset live
             * inventory.
             */
            $item->fill($itemData);

            if ($isNewItem) {
                $item->inventory = 0;
            }

            $item->save();

            $this->synchronizeSku($item, $itemLocationId);

            if ($isNewItem) {
                $createdCount++;
            } else {
                $updatedCount++;
            }
        }

        $this->command?->info('Items seeded successfully.');
        $this->command?->info('Configured: '.count($items));
        $this->command?->info('Created: '.$createdCount);
        $this->command?->info('Updated without resetting inventory: '.$updatedCount);
    }

    private function requireLocation(string $code): Location
    {
        return Location::query()
            ->where('code', $code)
            ->firstOrFail();
    }

    private function requireGeneralProductPostingGroup(
        string $code,
    ): GeneralProductPostingGroup {
        return GeneralProductPostingGroup::query()
            ->where('code', $code)
            ->firstOrFail();
    }

    private function requireInventoryPostingGroup(
        string $code,
    ): InventoryPostingGroup {
        return InventoryPostingGroup::query()
            ->where('code', $code)
            ->firstOrFail();
    }

    private function resolveInventoryPostingSetupId(
        int $inventoryPostingGroupId,
        int $locationId,
        string $itemCode,
    ): int {
        $setupId = InventoryPostingSetup::query()
            ->where(
                'inventory_posting_group_id',
                $inventoryPostingGroupId,
            )
            ->where('location_id', $locationId)
            ->value('id');

        if (! $setupId) {
            throw new RuntimeException(
                "Missing Inventory Posting Setup for item {$itemCode}, "
                ."inventory posting group {$inventoryPostingGroupId}, "
                ."and location {$locationId}.",
            );
        }

        return (int) $setupId;
    }

    private function resolveUnambiguousGeneralPostingSetupId(
        int $generalProductPostingGroupId,
    ): ?int {
        $setupIds = GeneralPostingSetup::query()
            ->where(
                'general_product_posting_group_id',
                $generalProductPostingGroupId,
            )
            ->limit(2)
            ->pluck('id');

        if ($setupIds->count() !== 1) {
            return null;
        }

        return (int) $setupIds->first();
    }

    private function synchronizeSku(Item $item, int $locationId): void
    {
        $location = Location::query()->findOrFail($locationId);

        $sku = ItemSku::query()->updateOrCreate(
            [
                'item_id' => $item->id,
                'location_id' => $location->id,
            ],
            [
                'sku_code' => $item->item_code.'-'.$location->code,
                'is_active' => true,
            ],
        );

        if ((int) $item->sku_id !== (int) $sku->id) {
            $item->forceFill([
                'sku_id' => $sku->id,
            ])->save();
        }
    }
}
