<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    private int $sortOrder = 0;

    /**
     * Seed operational inventory categories.
     *
     * These categories classify:
     * - Finished goods
     * - Semi-finished and WIP items
     * - Raw materials
     * - Packaging materials
     * - Consumables
     * - Maintenance and spare-parts items
     *
     * @throws \Throwable
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            /*
             * Reset the counter on every seeder execution so ordering remains
             * deterministic when the seeder is rerun.
             */
            $this->sortOrder = 0;

            /*
             * ================================================================
             * FINISHED GOODS
             * ================================================================
             */

            $finishedGoods = $this->createCategory([
                'category_code' => 'FG',
                'category_name' => 'Finished Goods',
                'category_type' => 'FINISHED_GOOD',
                'level' => 0,
                'description' => 'Completed products ready for storage, sale, transfer or distribution.',
            ]);

            $herbalProducts = $this->createCategory([
                'category_code' => 'FG-HERBAL',
                'category_name' => 'Herbal Products',
                'parent_id' => $finishedGoods->id,
                'category_type' => 'FINISHED_GOOD',
                'level' => 1,
                'description' => 'Finished herbal medicines, supplements and related products.',
            ]);

            $this->createCategory([
                'category_code' => 'FG-HERBAL-LIQ',
                'category_name' => 'Liquid Herbal Products',
                'parent_id' => $herbalProducts->id,
                'category_type' => 'FINISHED_GOOD',
                'level' => 2,
                'description' => 'Finished herbal products supplied in liquid form.',
            ]);

            $this->createCategory([
                'category_code' => 'FG-HERBAL-BOT',
                'category_name' => 'Bottled Herbal Products',
                'parent_id' => $herbalProducts->id,
                'category_type' => 'FINISHED_GOOD',
                'level' => 2,
                'description' => 'Finished herbal products packaged in bottles.',
            ]);

            $this->createCategory([
                'category_code' => 'FG-HERBAL-CTN',
                'category_name' => 'Cartoned Herbal Products',
                'parent_id' => $herbalProducts->id,
                'category_type' => 'FINISHED_GOOD',
                'level' => 2,
                'description' => 'Finished herbal products packed into cartons for wholesale or distribution.',
            ]);

            $this->createCategory([
                'category_code' => 'FG-RETAIL',
                'category_name' => 'Retail Finished Goods',
                'parent_id' => $finishedGoods->id,
                'category_type' => 'FINISHED_GOOD',
                'level' => 1,
                'description' => 'Finished products packaged and priced for retail sale.',
            ]);

            $this->createCategory([
                'category_code' => 'FG-WHOLESALE',
                'category_name' => 'Wholesale Finished Goods',
                'parent_id' => $finishedGoods->id,
                'category_type' => 'FINISHED_GOOD',
                'level' => 1,
                'description' => 'Finished products packed for wholesale customers and distributors.',
            ]);

            /*
             * ================================================================
             * SEMI-FINISHED GOODS AND WORK IN PROCESS
             * ================================================================
             */

            $semiFinished = $this->createCategory([
                'category_code' => 'SFG',
                'category_name' => 'Semi-Finished Goods',
                'category_type' => 'SEMI_FINISHED',
                'level' => 0,
                'description' => 'Manufactured intermediate items awaiting further production or packaging.',
            ]);

            $this->createCategory([
                'category_code' => 'SFG-EXT',
                'category_name' => 'Herbal Extracts',
                'parent_id' => $semiFinished->id,
                'category_type' => 'SEMI_FINISHED',
                'level' => 1,
                'description' => 'Extracted herbal materials awaiting mixing or formulation.',
            ]);

            $this->createCategory([
                'category_code' => 'SFG-MIX',
                'category_name' => 'Mixed Formulations',
                'parent_id' => $semiFinished->id,
                'category_type' => 'SEMI_FINISHED',
                'level' => 1,
                'description' => 'Mixed herbal formulations awaiting filling or final processing.',
            ]);

            $this->createCategory([
                'category_code' => 'SFG-BULK',
                'category_name' => 'Bulk Product',
                'parent_id' => $semiFinished->id,
                'category_type' => 'SEMI_FINISHED',
                'level' => 1,
                'description' => 'Bulk manufactured product awaiting filling, capping or packaging.',
            ]);

            $this->createCategory([
                'category_code' => 'SFG-FILLED',
                'category_name' => 'Filled Unpacked Products',
                'parent_id' => $semiFinished->id,
                'category_type' => 'SEMI_FINISHED',
                'level' => 1,
                'description' => 'Filled products awaiting labelling, sleeving, packing or cartoning.',
            ]);

            $this->createCategory([
                'category_code' => 'SFG-PACK',
                'category_name' => 'Intermediate Packs',
                'parent_id' => $semiFinished->id,
                'category_type' => 'SEMI_FINISHED',
                'level' => 1,
                'description' => 'Partially packaged products awaiting final carton packing.',
            ]);

            /*
             * ================================================================
             * RAW MATERIALS
             * ================================================================
             */

            $rawMaterials = $this->createCategory([
                'category_code' => 'RM',
                'category_name' => 'Raw Materials',
                'category_type' => 'RAW_MATERIAL',
                'level' => 0,
                'description' => 'Materials consumed directly or indirectly during manufacturing.',
            ]);

            $botanicalMaterials = $this->createCategory([
                'category_code' => 'RM-BOT',
                'category_name' => 'Botanical Raw Materials',
                'parent_id' => $rawMaterials->id,
                'category_type' => 'RAW_MATERIAL',
                'level' => 1,
                'description' => 'Plant-derived raw materials used in herbal production.',
            ]);

            $botanicalParts = [
                [
                    'code' => 'RM-BOT-ROOT',
                    'name' => 'Roots',
                    'description' => 'Plant roots used as manufacturing raw materials.',
                ],
                [
                    'code' => 'RM-BOT-LEAF',
                    'name' => 'Leaves',
                    'description' => 'Plant leaves used as manufacturing raw materials.',
                ],
                [
                    'code' => 'RM-BOT-BARK',
                    'name' => 'Barks',
                    'description' => 'Plant bark used as manufacturing raw materials.',
                ],
                [
                    'code' => 'RM-BOT-SEED',
                    'name' => 'Seeds',
                    'description' => 'Seeds used as manufacturing raw materials.',
                ],
                [
                    'code' => 'RM-BOT-FRUIT',
                    'name' => 'Fruits',
                    'description' => 'Whole or processed fruits used as manufacturing raw materials.',
                ],
                [
                    'code' => 'RM-BOT-FLOWER',
                    'name' => 'Flowers',
                    'description' => 'Flowers used as manufacturing raw materials.',
                ],
                [
                    'code' => 'RM-BOT-HERB',
                    'name' => 'Whole Herbs',
                    'description' => 'Whole or mixed herbs used as manufacturing raw materials.',
                ],
            ];

            foreach ($botanicalParts as $part) {
                $this->createCategory([
                    'category_code' => $part['code'],
                    'category_name' => $part['name'],
                    'parent_id' => $botanicalMaterials->id,
                    'category_type' => 'RAW_MATERIAL',
                    'level' => 2,
                    'description' => $part['description'],
                ]);
            }

            $physicalForms = $this->createCategory([
                'category_code' => 'RM-FORM',
                'category_name' => 'Raw Material Physical Forms',
                'parent_id' => $rawMaterials->id,
                'category_type' => 'RAW_MATERIAL',
                'level' => 1,
                'description' => 'Raw materials classified according to their physical form.',
            ]);

            $this->createCategory([
                'category_code' => 'RM-FORM-PWD',
                'category_name' => 'Powder',
                'parent_id' => $physicalForms->id,
                'category_type' => 'RAW_MATERIAL',
                'level' => 2,
                'description' => 'Powdered herbal ingredients, chemicals and additives.',
            ]);

            $this->createCategory([
                'category_code' => 'RM-FORM-GRAN',
                'category_name' => 'Granules',
                'parent_id' => $physicalForms->id,
                'category_type' => 'RAW_MATERIAL',
                'level' => 2,
                'description' => 'Granulated herbal ingredients, chemicals and production materials.',
            ]);

            $this->createCategory([
                'category_code' => 'RM-FORM-LIQ',
                'category_name' => 'Liquids',
                'parent_id' => $physicalForms->id,
                'category_type' => 'RAW_MATERIAL',
                'level' => 2,
                'description' => 'Liquid ingredients used in formulation and production.',
            ]);

            $this->createCategory([
                'category_code' => 'RM-FORM-CRYS',
                'category_name' => 'Crystals',
                'parent_id' => $physicalForms->id,
                'category_type' => 'RAW_MATERIAL',
                'level' => 2,
                'description' => 'Crystalline chemicals and ingredients used in production.',
            ]);

            $this->createCategory([
                'category_code' => 'RM-FORM-FLAKE',
                'category_name' => 'Flakes',
                'parent_id' => $physicalForms->id,
                'category_type' => 'RAW_MATERIAL',
                'level' => 2,
                'description' => 'Flaked raw materials used during manufacturing.',
            ]);

            $this->createCategory([
                'category_code' => 'RM-FORM-PASTE',
                'category_name' => 'Pastes and Concentrates',
                'parent_id' => $physicalForms->id,
                'category_type' => 'RAW_MATERIAL',
                'level' => 2,
                'description' => 'Pastes, concentrates and thick raw materials used in formulation.',
            ]);

            $chemicals = $this->createCategory([
                'category_code' => 'RM-CHEM',
                'category_name' => 'Chemicals and Additives',
                'parent_id' => $rawMaterials->id,
                'category_type' => 'RAW_MATERIAL',
                'level' => 1,
                'description' => 'Chemical ingredients, preservatives, sweeteners and formulation additives.',
            ]);

            $chemicalTypes = [
                [
                    'code' => 'RM-CHEM-PRES',
                    'name' => 'Preservatives',
                    'description' => 'Preservatives used to maintain product quality and stability.',
                ],
                [
                    'code' => 'RM-CHEM-SWEET',
                    'name' => 'Sweeteners',
                    'description' => 'Natural or artificial sweeteners used in product formulation.',
                ],
                [
                    'code' => 'RM-CHEM-FLAV',
                    'name' => 'Flavours',
                    'description' => 'Flavouring materials used in finished products.',
                ],
                [
                    'code' => 'RM-CHEM-COLOR',
                    'name' => 'Colours',
                    'description' => 'Colouring materials used in product formulation.',
                ],
                [
                    'code' => 'RM-CHEM-STAB',
                    'name' => 'Stabilisers',
                    'description' => 'Stabilisers used to maintain product consistency.',
                ],
                [
                    'code' => 'RM-CHEM-SOLV',
                    'name' => 'Solvents',
                    'description' => 'Water, alcohol and other approved formulation solvents.',
                ],
                [
                    'code' => 'RM-CHEM-ACT',
                    'name' => 'Active Ingredients',
                    'description' => 'Active chemical or botanical ingredients used in formulations.',
                ],
            ];

            foreach ($chemicalTypes as $chemical) {
                $this->createCategory([
                    'category_code' => $chemical['code'],
                    'category_name' => $chemical['name'],
                    'parent_id' => $chemicals->id,
                    'category_type' => 'RAW_MATERIAL',
                    'level' => 2,
                    'description' => $chemical['description'],
                ]);
            }

            /*
             * ================================================================
             * PACKAGING MATERIALS
             * ================================================================
             */

            $packagingMaterials = $this->createCategory([
                'category_code' => 'PKG',
                'category_name' => 'Packaging Materials',
                'category_type' => 'PACKAGING',
                'level' => 0,
                'description' => 'Primary, secondary and tertiary materials used to package products.',
            ]);

            $primaryPackaging = $this->createCategory([
                'category_code' => 'PKG-PRI',
                'category_name' => 'Primary Packaging',
                'parent_id' => $packagingMaterials->id,
                'category_type' => 'PACKAGING',
                'level' => 1,
                'description' => 'Packaging materials that directly contact or contain the product.',
            ]);

            $this->createCategory([
                'category_code' => 'PKG-PRI-PLAST',
                'category_name' => 'Plastic',
                'parent_id' => $primaryPackaging->id,
                'category_type' => 'PACKAGING',
                'level' => 2,
                'description' => 'Plastic bottles, containers, jars and other primary plastic packaging.',
            ]);

            $this->createCategory([
                'category_code' => 'PKG-PRI-GLASS',
                'category_name' => 'Glass',
                'parent_id' => $primaryPackaging->id,
                'category_type' => 'PACKAGING',
                'level' => 2,
                'description' => 'Glass bottles, jars and containers used for finished products.',
            ]);

            $this->createCategory([
                'category_code' => 'PKG-PRI-CAPS',
                'category_name' => 'Caps and Closures',
                'parent_id' => $primaryPackaging->id,
                'category_type' => 'PACKAGING',
                'level' => 2,
                'description' => 'Caps, rubber closures, seals and related closure materials.',
            ]);

            $this->createCategory([
                'category_code' => 'PKG-PRI-NYLON',
                'category_name' => 'Nylon',
                'parent_id' => $primaryPackaging->id,
                'category_type' => 'PACKAGING',
                'level' => 2,
                'description' => 'Nylon sachets, films and bags used for primary product packaging.',
            ]);

            $secondaryPackaging = $this->createCategory([
                'category_code' => 'PKG-SEC',
                'category_name' => 'Secondary Packaging',
                'parent_id' => $packagingMaterials->id,
                'category_type' => 'PACKAGING',
                'level' => 1,
                'description' => 'Packaging used to group, protect, identify or present primary packages.',
            ]);

            $this->createCategory([
                'category_code' => 'PKG-SEC-PAPER',
                'category_name' => 'Paper',
                'parent_id' => $secondaryPackaging->id,
                'category_type' => 'PACKAGING',
                'level' => 2,
                'description' => 'Paper-based labels, leaflets, wrappers and packaging materials.',
            ]);

            $this->createCategory([
                'category_code' => 'PKG-SEC-LABEL',
                'category_name' => 'Labels',
                'parent_id' => $secondaryPackaging->id,
                'category_type' => 'PACKAGING',
                'level' => 2,
                'description' => 'Printed labels and identification materials for finished products.',
            ]);

            $this->createCategory([
                'category_code' => 'PKG-SEC-SLEEVE',
                'category_name' => 'Shrink Sleeves',
                'parent_id' => $secondaryPackaging->id,
                'category_type' => 'PACKAGING',
                'level' => 2,
                'description' => 'PVC, plastic and printed shrink sleeves used for packaging.',
            ]);

            $this->createCategory([
                'category_code' => 'PKG-SEC-TRAY',
                'category_name' => 'Paper Trays',
                'parent_id' => $secondaryPackaging->id,
                'category_type' => 'PACKAGING',
                'level' => 2,
                'description' => 'Paper or cardboard trays used to group bottles or product units.',
            ]);

            $this->createCategory([
                'category_code' => 'PKG-SEC-BOX',
                'category_name' => 'Boxes',
                'parent_id' => $secondaryPackaging->id,
                'category_type' => 'PACKAGING',
                'level' => 2,
                'description' => 'Individual product boxes and secondary packaging boxes.',
            ]);

            $this->createCategory([
                'category_code' => 'PKG-SEC-FILM',
                'category_name' => 'Plastic Films',
                'parent_id' => $secondaryPackaging->id,
                'category_type' => 'PACKAGING',
                'level' => 2,
                'description' => 'Plastic films and wrapping materials used in product packaging.',
            ]);

            $tertiaryPackaging = $this->createCategory([
                'category_code' => 'PKG-TER',
                'category_name' => 'Tertiary Packaging',
                'parent_id' => $packagingMaterials->id,
                'category_type' => 'PACKAGING',
                'level' => 1,
                'description' => 'Packaging used for bulk storage, transport and distribution.',
            ]);

            $this->createCategory([
                'category_code' => 'PKG-TER-CTN',
                'category_name' => 'Cartons',
                'parent_id' => $tertiaryPackaging->id,
                'category_type' => 'PACKAGING',
                'level' => 2,
                'description' => 'Corrugated cartons and shipping boxes used for finished goods.',
            ]);

            $this->createCategory([
                'category_code' => 'PKG-TER-NYLON',
                'category_name' => 'Bulk Nylon Bags',
                'parent_id' => $tertiaryPackaging->id,
                'category_type' => 'PACKAGING',
                'level' => 2,
                'description' => 'Large nylon bags used for bulk handling, protection or transport.',
            ]);

            $this->createCategory([
                'category_code' => 'PKG-TER-WRAP',
                'category_name' => 'Stretch and Shrink Wrap',
                'parent_id' => $tertiaryPackaging->id,
                'category_type' => 'PACKAGING',
                'level' => 2,
                'description' => 'Stretch film and shrink wrapping used for palletised goods.',
            ]);

            $this->createCategory([
                'category_code' => 'PKG-TER-PALLET',
                'category_name' => 'Pallets',
                'parent_id' => $tertiaryPackaging->id,
                'category_type' => 'PACKAGING',
                'level' => 2,
                'description' => 'Wooden, plastic or reusable pallets used for storage and transport.',
            ]);

            /*
             * ================================================================
             * CONSUMABLES AND SUPPLIES
             * ================================================================
             */

            $consumables = $this->createCategory([
                'category_code' => 'CON',
                'category_name' => 'Consumables and Supplies',
                'category_type' => 'CONSUMABLE',
                'level' => 0,
                'description' => 'Materials consumed during operations but not normally part of the finished product.',
            ]);

            $consumableTypes = [
                [
                    'code' => 'CON-CLEAN',
                    'name' => 'Cleaning Materials',
                    'description' => 'Cleaning chemicals, detergents and sanitation supplies.',
                ],
                [
                    'code' => 'CON-PPE',
                    'name' => 'Personal Protective Equipment',
                    'description' => 'Gloves, masks, aprons and other protective equipment.',
                ],
                [
                    'code' => 'CON-LAB',
                    'name' => 'Laboratory Consumables',
                    'description' => 'Testing materials and consumables used by quality control.',
                ],
                [
                    'code' => 'CON-OFFICE',
                    'name' => 'Office Supplies',
                    'description' => 'Administrative and stationery supplies.',
                ],
                [
                    'code' => 'CON-PROD',
                    'name' => 'Production Consumables',
                    'description' => 'Operational supplies consumed during production activities.',
                ],
            ];

            foreach ($consumableTypes as $consumable) {
                $this->createCategory([
                    'category_code' => $consumable['code'],
                    'category_name' => $consumable['name'],
                    'parent_id' => $consumables->id,
                    'category_type' => 'CONSUMABLE',
                    'level' => 1,
                    'description' => $consumable['description'],
                ]);
            }

            /*
             * ================================================================
             * MAINTENANCE MATERIALS AND SPARE PARTS
             * ================================================================
             */

            $maintenance = $this->createCategory([
                'category_code' => 'MRO',
                'category_name' => 'Maintenance, Repair and Operations',
                'category_type' => 'SPARE_PART',
                'level' => 0,
                'description' => 'Maintenance materials, machine parts, tools and engineering supplies.',
            ]);

            $maintenanceTypes = [
                [
                    'code' => 'MRO-SPARES',
                    'name' => 'Machine Spare Parts',
                    'description' => 'Replacement parts for manufacturing machines and equipment.',
                ],
                [
                    'code' => 'MRO-TOOLS',
                    'name' => 'Tools',
                    'description' => 'Hand tools, power tools and workshop equipment.',
                ],
                [
                    'code' => 'MRO-ELEC',
                    'name' => 'Electrical Materials',
                    'description' => 'Electrical components, cables, switches and control parts.',
                ],
                [
                    'code' => 'MRO-MECH',
                    'name' => 'Mechanical Materials',
                    'description' => 'Bearings, belts, seals and other mechanical components.',
                ],
                [
                    'code' => 'MRO-LUBE',
                    'name' => 'Lubricants',
                    'description' => 'Machine oils, grease and approved maintenance lubricants.',
                ],
            ];

            foreach ($maintenanceTypes as $maintenanceType) {
                $this->createCategory([
                    'category_code' => $maintenanceType['code'],
                    'category_name' => $maintenanceType['name'],
                    'parent_id' => $maintenance->id,
                    'category_type' => 'SPARE_PART',
                    'level' => 1,
                    'description' => $maintenanceType['description'],
                ]);
            }
        });

        $this->command?->info('Categories seeded successfully.');
        $this->command?->info(
            'Total configured categories: '.$this->sortOrder,
        );
    }

    /**
     * Create or update one category while preserving its stable code.
     */
    private function createCategory(array $data): Category
    {
        $parentId = $data['parent_id'] ?? null;
        $level = (int) ($data['level'] ?? 0);

        $hierarchyPath = (string) $data['category_code'];

        if ($parentId !== null) {
            $parent = Category::query()->findOrFail($parentId);

            $hierarchyPath = $parent->hierarchy_path
                .'.'
                .$data['category_code'];
        }

        $attributes = [
            'category_name' => $data['category_name'],
            'parent_id' => $parentId,
            'category_type' => $data['category_type'],
            'level' => $level,
            'hierarchy_path' => $hierarchyPath,
            'sort_order' => $this->sortOrder++,
            'description' => $data['description'] ?? null,
            'attributes' => $data['attributes'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];

        return Category::query()->updateOrCreate(
            [
                'category_code' => $data['category_code'],
            ],
            $attributes,
        );
    }
}
