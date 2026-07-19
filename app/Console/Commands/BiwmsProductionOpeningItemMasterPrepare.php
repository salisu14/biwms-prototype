<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AccountCategory;
use App\Enums\AccountStructuralType;
use App\Enums\IncomeBalanceType;
use App\Models\ChartOfAccount;
use App\Models\GeneralProductPostingGroup;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\ItemUomAssignment;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\ValueEntry;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

#[Signature('biwms:production-opening-item-master-prepare
    {--apply : Apply safe item-master preparation changes}
    {--details : Show reference-impact audit rows}
    {--export= : Write JSON preparation report to a path}
    {--bottle-cost= : Verified base-unit cost for BOTTLE-60ML, in PCS}
    {--cap-cost= : Verified base-unit cost for CAP-60ML, in PCS}')]
#[Description('Prepare production item masters for the operator-approved opening inventory manifest.')]
class BiwmsProductionOpeningItemMasterPrepare extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $bottleCost = $this->verifiedCostOption('bottle-cost');
        $capCost = $this->verifiedCostOption('cap-cost');

        $report = [
            'mode' => $this->option('apply') ? 'apply' : 'dry-run',
            'manifest' => 'PROD-PHYSICAL-OPENING-2026-V1',
            'unresolved_exclusions' => [
                [
                    'source_quantity' => '158',
                    'source_uom' => 'CT',
                    'reason' => 'Excluded until exact item identity and conversion are confirmed.',
                ],
            ],
            'reference_impact_audit' => $this->referenceImpactAudit(),
            'planned_changes' => $this->plannedChanges($bottleCost, $capCost),
            'approved_cost_table' => $this->approvedCostTable($bottleCost, $capCost),
        ];

        if ($exportPath = $this->option('export')) {
            $this->exportReport($report, (string) $exportPath);
        }

        $this->info('BIWMS Production Opening Item Master Preparation');
        $this->line($this->option('apply') ? 'Mode: apply.' : 'Mode: dry-run. No item master data was changed.');
        if ($exportPath) {
            $this->line("Exported JSON report to {$exportPath}.");
        }

        if ($this->option('details')) {
            foreach ($report['reference_impact_audit'] as $auditRow) {
                $this->line(sprintf(
                    ' - item=%s uom=%s ledger=%s value=%s refs=%s safe_edit=%s action=%s',
                    $auditRow['item_code'],
                    $auditRow['current_base_uom'] ?? 'N/A',
                    $auditRow['ledger_count'],
                    $auditRow['value_entry_count'],
                    $auditRow['total_references'],
                    $auditRow['safe_to_edit_directly'] ? 'yes' : 'no',
                    $auditRow['recommended_action'],
                ));
            }
        }

        if (! $this->option('apply')) {
            return self::SUCCESS;
        }

        $missingCostOptions = [];
        if (! $bottleCost) {
            $missingCostOptions[] = '--bottle-cost';
        }
        if (! $capCost) {
            $missingCostOptions[] = '--cap-cost';
        }
        if ($missingCostOptions !== []) {
            $this->error('Cannot apply preparation without verified base-unit costs: '.implode(', ', $missingCostOptions).'.');

            return self::FAILURE;
        }

        $unsafeRows = collect($report['reference_impact_audit'])
            ->filter(fn (array $row): bool => $row['item_code'] !== '2500' && ! $row['safe_to_edit_directly'])
            ->values();

        if ($unsafeRows->isNotEmpty()) {
            $this->error('Cannot apply preparation because one or more existing mapped items have ledger/value history.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($bottleCost, $capCost): void {
            $this->prepareMasterData($bottleCost, $capCost);
        });

        $this->info('Prepared production opening item masters. The opening repair itself was not executed.');

        return self::SUCCESS;
    }

    private function verifiedCostOption(string $option): ?string
    {
        $value = $this->option($option);
        if ($value === null || $value === '') {
            return null;
        }

        $cost = DecimalMath::unitCost($value);

        return DecimalMath::isPositive($cost) ? $cost : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function referenceImpactAudit(): array
    {
        return collect($this->affectedItemCodes())
            ->map(function (string $itemCode): array {
                $item = Item::query()->with('baseUom')->where('item_code', $itemCode)->first();
                $references = $item ? $this->referenceCounts((int) $item->id, $item->item_code) : [];
                $ledgerCount = $item ? ItemLedgerEntry::query()->where('item_id', $item->id)->count() : 0;
                $valueEntryCount = $item ? ValueEntry::query()->where('item_no', $item->item_code)->count() : 0;
                $safeToEdit = $item === null || ($ledgerCount === 0 && $valueEntryCount === 0);

                return [
                    'item_code' => $itemCode,
                    'description' => $item?->description,
                    'current_base_uom' => $item?->baseUom?->uom_code,
                    'references' => $references,
                    'total_references' => array_sum($references),
                    'ledger_count' => $ledgerCount,
                    'value_entry_count' => $valueEntryCount,
                    'safe_to_edit_directly' => $safeToEdit,
                    'recommended_action' => $this->recommendedAction($itemCode, $item, $safeToEdit),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function affectedItemCodes(): array
    {
        return [
            'BOTTLE-60ML',
            'CAP-60ML',
            '2500',
            '2600',
            '2700',
            '2800',
            '2900',
            '2100',
            '2200',
            '2300',
            '2400',
            '2410',
        ];
    }

    /**
     * @return array<string, int>
     */
    private function referenceCounts(int $itemId, string $itemCode): array
    {
        return [
            'production_bom_lines' => $this->countTableReferences('production_bom_lines', 'item_id', $itemId),
            'production_bom_version_lines' => $this->countTableReferences('production_bom_version_lines', 'item_id', $itemId),
            'production_order_components' => $this->countTableReferences('production_order_components', 'item_id', $itemId),
            'production_journal_lines' => $this->countTableReferences('production_journal_lines', 'item_id', $itemId),
            'item_uom_assignments' => $this->countTableReferences('item_uom_assignments', 'item_id', $itemId),
            'purchase_order_lines' => $this->countTableReferences('purchase_order_lines', 'item_id', $itemId),
            'purchase_invoice_lines' => $this->countTableReferences('purchase_invoice_lines', 'item_id', $itemId),
            'purchase_receipt_lines' => $this->countTableReferences('purchase_receipt_lines', 'item_id', $itemId),
            'sales_order_lines' => $this->countTableReferences('sales_order_lines', 'item_id', $itemId),
            'sales_invoice_lines' => $this->countTableReferences('sales_invoice_lines', 'item_id', $itemId),
            'item_ledger_entries' => $this->countTableReferences('item_ledger_entries', 'item_id', $itemId),
            'value_entries' => $this->countValueEntryReferences($itemCode),
            'inventory_adjustment_lines' => $this->countTableReferences('inventory_adjustment_lines', 'item_id', $itemId),
            'physical_inventory_lines' => $this->countTableReferences('physical_inventory_lines', 'item_id', $itemId),
            'opening_inventory_lines' => $this->countTableReferences('opening_inventory_lines', 'item_id', $itemId),
        ];
    }

    private function countTableReferences(string $table, string $column, int $itemId): int
    {
        if (! DB::getSchemaBuilder()->hasTable($table) || ! DB::getSchemaBuilder()->hasColumn($table, $column)) {
            return 0;
        }

        return (int) DB::table($table)->where($column, $itemId)->count();
    }

    private function countValueEntryReferences(string $itemCode): int
    {
        if (! DB::getSchemaBuilder()->hasTable('value_entries')) {
            return 0;
        }

        return (int) DB::table('value_entries')->where('item_no', $itemCode)->count();
    }

    private function recommendedAction(string $itemCode, ?Item $item, bool $safeToEdit): string
    {
        if (in_array($itemCode, ['BOTTLE-60ML', 'CAP-60ML'], true)) {
            return $item ? 'Confirm dedicated PCS item and verified cost.' : 'Create dedicated PCS item with verified base-unit cost.';
        }

        if ($itemCode === '2500') {
            return 'Preserve combined Rubber & Cap item. Do not use it for Bottle Cap opening stock.';
        }

        if (! $safeToEdit) {
            return 'Do not edit directly. Create replacement item and leave historical transactional references intact.';
        }

        return match ($itemCode) {
            '2800', '2900' => 'Safe to correct base UOM to PCS and preserve BOM references because item id remains unchanged.',
            '2410' => 'Safe to add item-scoped BG to G conversion and correct base-unit cost if current cost is bag cost.',
            default => 'Confirm base UOM, location, and verified base-unit cost.',
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function plannedChanges(?string $bottleCost, ?string $capCost): array
    {
        return [
            [
                'item_code' => 'BOTTLE-60ML',
                'action' => 'create_or_update',
                'base_uom' => 'PCS',
                'location_code' => 'GBS-FGN',
                'inventory_posting_group' => 'PACKAGING',
                'verified_base_unit_cost' => $bottleCost,
            ],
            [
                'item_code' => 'CAP-60ML',
                'action' => 'create_or_update',
                'base_uom' => 'PCS',
                'location_code' => 'GBS-FGN',
                'inventory_posting_group' => 'PACKAGING',
                'verified_base_unit_cost' => $capCost,
            ],
            [
                'item_code' => '2500',
                'action' => 'preserve',
                'note' => 'Combined Rubber & Cap remains untouched.',
            ],
            [
                'item_code' => '2800',
                'action' => 'correct_base_uom_if_no_ledger_history',
                'base_uom' => 'PCS',
                'location_code' => 'GBS-FGN',
            ],
            [
                'item_code' => '2900',
                'action' => 'correct_base_uom_if_no_ledger_history',
                'base_uom' => 'PCS',
                'location_code' => 'GBS-FGN',
            ],
            [
                'item_code' => '2410',
                'action' => 'add_item_scoped_uom_conversion',
                'source_uom' => 'BG',
                'base_uom' => 'G',
                'conversion_factor' => '25000.000000000000',
                'verified_base_unit_cost' => '0.80000000',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function approvedCostTable(?string $bottleCost, ?string $capCost): array
    {
        return collect([
            ['BOTTLE-60ML', '60 ml Bottle', 'PCS', $bottleCost, 'verified operator-supplied base PCS cost', '149184'],
            ['CAP-60ML', 'Bottle Cap', 'PCS', $capCost, 'verified operator-supplied base PCS cost', '149184'],
            ['2600', 'Label', 'PCS', '20.00000000', 'current item cost reviewed as base PCS cost', '149184'],
            ['2800', 'Paper Tray', 'PCS', '70.14000000', 'current item cost reviewed as base PCS cost', '12432'],
            ['2700', 'Shrink Sleeve', 'PCS', '150.00000000', 'current item cost reviewed as base PCS cost', '12432'],
            ['2900', 'Mai Sasanci 3-ply Box', 'PCS', '723.00000000', 'current item cost reviewed as base PCS cost', '518'],
            ['2100', 'Sodium Saccharine', 'G', '8.80000000', 'current item cost reviewed as base G cost', '3400'],
            ['2200', 'Ginseng', 'G', '778.50000000', 'current item cost reviewed as base G cost', '8840'],
            ['2300', 'Yohimbine', 'G', '336.00000000', 'current item cost reviewed as base G cost', '20570'],
            ['2400', 'Sodium Benzoate', 'G', '2.80000000', 'current item cost reviewed as base G cost', '17000'],
            ['2410', 'Ficus Carica', 'G', '0.80000000', '20000 per 25kg bag / 25000g', '75000'],
        ])->map(function (array $row): array {
            $cost = $row[3] ? DecimalMath::unitCost($row[3]) : null;
            $quantity = DecimalMath::quantity($row[5]);

            return [
                'item_code' => $row[0],
                'item' => $row[1],
                'base_uom' => $row[2],
                'approved_base_unit_cost' => $cost,
                'cost_source' => $row[4],
                'opening_quantity' => $quantity,
                'opening_value' => $cost
                    ? DecimalMath::amount(DecimalMath::mul($quantity, $cost, DecimalPrecision::AMOUNT_SCALE))
                    : null,
            ];
        })->all();
    }

    private function prepareMasterData(string $bottleCost, string $capCost): void
    {
        $rawLocation = $this->location('GBS-RAWMAT', 'Gabasawa Raw Materials Store');
        $packagingLocation = $this->location('GBS-FGN', 'Gabasawa Packaging Store');
        $pcs = $this->uom('PCS', 'Pieces', true, '1.000000000000');
        $gram = $this->uom('G', 'Gram', true, '1.000000000000');
        $bag = $this->uom('BG', 'Bag', false, '25000.000000000000');
        $packagingPostingGroup = InventoryPostingGroup::query()->firstOrCreate(
            ['code' => 'PACKAGING'],
            ['description' => 'Packing Materials', 'blocked' => false],
        );
        $rawPostingGroup = InventoryPostingGroup::query()->firstOrCreate(
            ['code' => 'RAW'],
            ['description' => 'Raw Materials', 'blocked' => false],
        );

        $packagingProductGroup = GeneralProductPostingGroup::query()->firstOrCreate(
            ['code' => 'PACKAGING'],
            ['description' => 'Packaging'],
        );
        GeneralProductPostingGroup::query()->firstOrCreate(
            ['code' => 'RAW'],
            ['description' => 'Raw Materials'],
        );

        $this->ensureOpeningEquityAccount();
        $this->ensureInventoryPostingSetup($packagingPostingGroup, $packagingLocation);
        $this->ensureInventoryPostingSetup($rawPostingGroup, $rawLocation);

        $this->createOrUpdateItem(
            itemCode: 'BOTTLE-60ML',
            description: '60 ml Bottle',
            baseUom: $pcs,
            location: $packagingLocation,
            inventoryPostingGroup: $packagingPostingGroup,
            generalProductPostingGroup: $packagingProductGroup,
            unitCost: $bottleCost,
        );
        $this->createOrUpdateItem(
            itemCode: 'CAP-60ML',
            description: 'Bottle Cap',
            baseUom: $pcs,
            location: $packagingLocation,
            inventoryPostingGroup: $packagingPostingGroup,
            generalProductPostingGroup: $packagingProductGroup,
            unitCost: $capCost,
        );

        $this->updateExistingManifestItem('2600', $pcs, $packagingLocation, '20.00000000');
        $this->updateExistingManifestItem('2700', $pcs, $packagingLocation, '150.00000000');
        $this->updateExistingManifestItem('2800', $pcs, $packagingLocation, '70.14000000');
        $this->updateExistingManifestItem('2900', $pcs, $packagingLocation, '723.00000000');
        $this->updateExistingManifestItem('2100', $gram, $rawLocation, '8.80000000');
        $this->updateExistingManifestItem('2200', $gram, $rawLocation, '778.50000000');
        $this->updateExistingManifestItem('2300', $gram, $rawLocation, '336.00000000');
        $this->updateExistingManifestItem('2400', $gram, $rawLocation, '2.80000000');
        $ficus = $this->updateExistingManifestItem('2410', $gram, $rawLocation, '0.80000000');

        $this->syncItemUom($ficus, $bag, 'PURCHASE', '25000.000000000000', false);

        if ($item2500 = Item::query()->where('item_code', '2500')->first()) {
            $item2500->forceFill([
                'blocked' => false,
            ])->save();
        }
    }

    private function location(string $code, string $name): Location
    {
        return Location::query()->firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'blocked' => false, 'is_active' => true],
        );
    }

    private function uom(string $code, string $description, bool $isBase, string $conversionFactor): UnitOfMeasure
    {
        return UnitOfMeasure::query()->updateOrCreate(
            ['uom_code' => $code],
            [
                'description' => $description,
                'is_base_uom' => $isBase,
                'conversion_factor' => $conversionFactor,
            ],
        );
    }

    private function ensureInventoryPostingSetup(InventoryPostingGroup $group, Location $location): void
    {
        $inventoryAccount = ChartOfAccount::query()
            ->where('account_number', '13110')
            ->first()
            ?? ChartOfAccount::query()->create([
                'account_number' => '13110',
                'name' => 'Raw and Packaging Inventory',
                'structural_type' => AccountStructuralType::POSTING,
                'account_category' => AccountCategory::INVENTORY,
                'income_balance' => IncomeBalanceType::BALANCE_SHEET,
                'direct_posting' => true,
                'blocked' => false,
            ]);

        InventoryPostingSetup::query()->updateOrCreate(
            [
                'inventory_posting_group_id' => $group->id,
                'location_id' => $location->id,
            ],
            [
                'inventory_account_id' => $inventoryAccount->id,
                'inventory_account_interim_id' => $inventoryAccount->id,
            ],
        );
    }

    private function ensureOpeningEquityAccount(): void
    {
        ChartOfAccount::query()->firstOrCreate(
            ['account_number' => '30100'],
            [
                'name' => 'Opening Balance Equity',
                'structural_type' => AccountStructuralType::POSTING,
                'account_category' => AccountCategory::EQUITY,
                'income_balance' => IncomeBalanceType::BALANCE_SHEET,
                'direct_posting' => true,
                'blocked' => false,
            ],
        );
    }

    private function createOrUpdateItem(
        string $itemCode,
        string $description,
        UnitOfMeasure $baseUom,
        Location $location,
        InventoryPostingGroup $inventoryPostingGroup,
        GeneralProductPostingGroup $generalProductPostingGroup,
        string $unitCost,
    ): Item {
        $item = Item::query()->firstOrNew(['item_code' => $itemCode]);
        $item->fill([
            'description' => $description,
            'description_2' => $description,
            'base_uom_id' => $baseUom->id,
            'location_id' => $location->id,
            'inventory_posting_group_id' => $inventoryPostingGroup->id,
            'general_product_posting_group_id' => $generalProductPostingGroup->id,
            'unit_cost' => $unitCost,
            'standard_cost' => $unitCost,
            'last_direct_cost' => $unitCost,
            'unit_price' => $unitCost,
            'blocked' => false,
        ]);

        if (! $item->exists) {
            $item->inventory = DecimalMath::quantity(0);
        }

        $item->save();

        $this->syncItemUom($item, $baseUom, 'BASE', '1.000000000000', true);
        $this->syncItemUom($item, $baseUom, 'PURCHASE', '1.000000000000', true);

        return $item->fresh('baseUom');
    }

    private function updateExistingManifestItem(string $itemCode, UnitOfMeasure $baseUom, Location $location, string $unitCost): Item
    {
        $item = Item::query()->where('item_code', $itemCode)->firstOrFail();

        $item->forceFill([
            'base_uom_id' => $baseUom->id,
            'location_id' => $location->id,
            'unit_cost' => $unitCost,
            'standard_cost' => $unitCost,
            'last_direct_cost' => $unitCost,
            'unit_price' => $unitCost,
            'blocked' => false,
        ])->save();

        $this->syncItemUom($item, $baseUom, 'BASE', '1.000000000000', true);

        return $item->fresh('baseUom');
    }

    private function syncItemUom(Item $item, UnitOfMeasure $uom, string $type, string $conversionFactor, bool $isDefault): void
    {
        ItemUomAssignment::query()->updateOrCreate(
            [
                'item_id' => $item->id,
                'uom_id' => $uom->id,
                'uom_type' => $type,
            ],
            [
                'conversion_factor' => $conversionFactor,
                'is_default' => $isDefault,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function exportReport(array $report, string $path): void
    {
        $absolutePath = str_starts_with($path, DIRECTORY_SEPARATOR)
            ? $path
            : base_path($path);

        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
