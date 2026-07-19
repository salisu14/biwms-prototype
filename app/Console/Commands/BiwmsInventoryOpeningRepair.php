<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ChartOfAccount;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\OpeningInventory;
use App\Models\UnitOfMeasure;
use App\Services\Inventory\OpeningInventoryService;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('biwms:inventory-opening-repair {--details : Show detailed repair plan rows} {--export= : Write the JSON repair plan to a file path} {--apply : Apply unambiguous opening stock repairs} {--item= : Limit to one item code or id} {--location= : Limit to one location code or id} {--manifest= : Versioned operator-approved manifest, e.g. PROD-PHYSICAL-OPENING-2026-V1}')]
#[Description('Report and optionally repair cache-only opening inventory mismatches.')]
class BiwmsInventoryOpeningRepair extends Command
{
    private const PROD_PHYSICAL_MANIFEST = 'PROD-PHYSICAL-OPENING-2026-V1';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('manifest')) {
            return $this->handleManifestRepair((string) $this->option('manifest'));
        }

        $findings = $this->cacheOnlyFindings();
        $report = [
            'mode' => $this->option('apply') ? 'apply' : 'dry-run',
            'findings' => $findings,
        ];

        if ($exportPath = $this->option('export')) {
            $this->exportReport($report, (string) $exportPath);
        }

        $this->info('BIWMS Inventory Opening Repair');
        $this->line($this->option('apply') ? 'Mode: apply.' : 'Mode: dry-run. No inventory data was changed.');
        if ($exportPath) {
            $this->line("Exported JSON report to {$exportPath}.");
        }
        $this->line('Findings: '.count($findings));

        if ($this->option('details')) {
            foreach ($findings as $finding) {
                $this->line(sprintf(
                    ' - [%s] %s item=%s stock=%s ledger=%s diff=%s repairable=%s',
                    $finding['severity'],
                    $finding['classification'],
                    $finding['item_code'],
                    $finding['stock_quantity'],
                    $finding['ledger_quantity'],
                    $finding['difference'],
                    $finding['repairable'] ? 'yes' : 'no',
                ));
            }
        }

        if (! $this->option('apply') || $findings === []) {
            return self::SUCCESS;
        }

        if (app()->environment('production')) {
            $this->error('Refusing to apply opening inventory repair in production.');

            return self::FAILURE;
        }

        $repairableFindings = collect($findings)->where('repairable', true)->values();
        if ($repairableFindings->count() !== count($findings)) {
            $this->error('Refusing to apply repair because one or more findings are ambiguous.');

            return self::FAILURE;
        }

        $documentNumber = 'OPREP-'.now()->format('ymdHis');
        $document = app(OpeningInventoryService::class)->createDraft(
            documentNumber: $documentNumber,
            source: 'REPAIR_OPENING_STOCK',
            postingDate: now()->toDateString(),
            lines: $repairableFindings->map(fn (array $finding): array => [
                'item_id' => $finding['item_id'],
                'location_id' => $finding['location_id'],
                'unit_of_measure_id' => $finding['unit_of_measure_id'],
                'unit_of_measure_code' => $finding['unit_of_measure_code'],
                'quantity' => $finding['difference'],
                'unit_cost' => $finding['unit_cost'],
            ])->all(),
            description: 'Controlled repair for cache-only opening inventory mismatches.',
        );

        app(OpeningInventoryService::class)->post($document);
        $this->info("Applied opening inventory repair via {$document->document_number}.");

        return self::SUCCESS;
    }

    private function handleManifestRepair(string $manifest): int
    {
        if ($manifest !== self::PROD_PHYSICAL_MANIFEST) {
            $this->error("Unknown opening inventory manifest [{$manifest}].");

            return self::FAILURE;
        }

        if ($this->option('apply')) {
            $existingDocument = OpeningInventory::query()
                ->where('document_number', 'POPEN-2026-V1')
                ->where('status', OpeningInventory::STATUS_POSTED)
                ->first();

            if ($existingDocument) {
                $this->info("Approved physical opening inventory {$existingDocument->document_number} is already posted; skipping.");

                return self::SUCCESS;
            }
        }

        $findings = $this->manifestFindings();
        $repairableFindings = collect($findings)->where('repairable', true)->values();
        $totalValue = $this->sumOpeningValue($findings);
        $repairableTotalValue = $this->sumOpeningValue($repairableFindings->all());
        $report = [
            'mode' => $this->option('apply') ? 'apply' : 'dry-run',
            'manifest' => $manifest,
            'document_number' => 'POPEN-2026-V1',
            'source' => $manifest,
            'excluded_lines' => [
                [
                    'item' => 'Carton',
                    'source_quantity' => '158',
                    'source_uom' => 'CT',
                    'reason' => 'Excluded until the operator confirms the item identity and CT-to-base-UOM conversion.',
                ],
            ],
            'total_proposed_opening_inventory_value' => $totalValue,
            'total_repairable_opening_inventory_value' => $repairableTotalValue,
            'findings' => $findings,
        ];

        if ($exportPath = $this->option('export')) {
            $this->exportReport($report, (string) $exportPath);
        }

        $this->info('BIWMS Inventory Opening Repair');
        $this->line("Manifest: {$manifest}");
        $this->line($this->option('apply') ? 'Mode: apply.' : 'Mode: dry-run. No inventory data was changed.');
        if ($exportPath ?? null) {
            $this->line("Exported JSON report to {$exportPath}.");
        }
        $this->line('Findings: '.count($findings));
        $this->line("Total proposed opening inventory value: {$totalValue}");
        $this->line("Total repairable opening inventory value: {$repairableTotalValue}");
        $this->line('Excluded unresolved lines: Carton 158 CT.');

        if ($this->option('details')) {
            foreach ($findings as $finding) {
                $this->line(sprintf(
                    ' - [%s] item=%s old_cache=%s approved=%s %s source=%s %s conversion=%s location=%s cost=%s value=%s inv_account=%s equity_account=%s repairable=%s reasons=%s',
                    $finding['severity'],
                    $finding['item_code'] ?? $finding['manifest_item_code'],
                    $finding['old_incorrect_cache'],
                    $finding['approved_physical_quantity'],
                    $finding['approved_base_uom'],
                    $finding['source_quantity'] ?? $finding['approved_physical_quantity'],
                    $finding['source_uom'] ?? $finding['approved_base_uom'],
                    $finding['conversion_factor'] ?? '1.000000000000',
                    $finding['location_code'],
                    $finding['approved_base_unit_cost'] ?? 'N/A',
                    $finding['opening_value'],
                    $finding['inventory_account'] ?? 'N/A',
                    $finding['opening_equity_account'] ?? 'N/A',
                    $finding['repairable'] ? 'yes' : 'no',
                    implode(',', $finding['reason_codes']),
                ));
            }
        }

        if (! $this->option('apply')) {
            return self::SUCCESS;
        }

        if ($repairableFindings->count() !== count($findings)) {
            $this->error('Refusing to apply manifest repair because one or more approved lines are not repairable.');

            return self::FAILURE;
        }

        $document = app(OpeningInventoryService::class)->createDraft(
            documentNumber: 'POPEN-2026-V1',
            source: $manifest,
            postingDate: now()->toDateString(),
            lines: $repairableFindings->map(fn (array $finding): array => [
                'item_id' => $finding['item_id'],
                'location_id' => $finding['location_id'],
                'unit_of_measure_id' => $finding['source_unit_of_measure_id'] ?? $finding['unit_of_measure_id'],
                'unit_of_measure_code' => $finding['source_uom'] ?? $finding['approved_base_uom'],
                'quantity' => $finding['source_quantity'] ?? $finding['approved_physical_quantity'],
                'unit_cost' => $finding['approved_base_unit_cost'],
            ])->all(),
            description: 'Operator-approved physical opening inventory correction.',
        );

        app(OpeningInventoryService::class)->post($document);
        $this->info("Applied approved physical opening inventory repair via {$document->document_number}.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cacheOnlyFindings(): array
    {
        $query = Item::query()
            ->with(['baseUom', 'location'])
            ->select('items.*')
            ->selectSub(
                ItemLedgerEntry::query()
                    ->selectRaw('COALESCE(SUM(quantity), 0)')
                    ->whereColumn('item_ledger_entries.item_id', 'items.id'),
                'ledger_quantity'
            )
            ->whereRaw('COALESCE(items.inventory, 0) <> 0');

        if ($item = $this->option('item')) {
            $query->where(function ($query) use ($item): void {
                $query->where('item_code', $item);
                if (is_numeric($item)) {
                    $query->orWhereKey((int) $item);
                }
            });
        }

        if ($location = $this->option('location')) {
            $query->whereHas('location', function ($query) use ($location): void {
                $query->where('code', $location);
                if (is_numeric($location)) {
                    $query->orWhereKey((int) $location);
                }
            });
        }

        return $query
            ->orderBy('item_code')
            ->get()
            ->map(function (Item $item): array {
                $stockQuantity = DecimalMath::quantity($item->inventory);
                $ledgerQuantity = DecimalMath::quantity($item->ledger_quantity ?? 0);
                $difference = DecimalMath::sub($stockQuantity, $ledgerQuantity, 8);
                $hasAnyLedger = ! DecimalMath::isZero($ledgerQuantity);
                $unitCost = DecimalMath::unitCost($item->unit_cost ?? $item->standard_cost ?? 0);
                $repairable = DecimalMath::isPositive($difference)
                    && ! $hasAnyLedger
                    && DecimalMath::isPositive($unitCost)
                    && $item->location_id !== null;

                return [
                    'classification' => $repairable ? 'cache_only_opening_stock' : 'ambiguous_stock_cache_mismatch',
                    'severity' => $repairable ? 'warning' : 'critical',
                    'item_id' => $item->id,
                    'item_code' => $item->item_code,
                    'description' => $item->description,
                    'location_id' => $item->location_id,
                    'location_code' => $item->location?->code,
                    'unit_of_measure_id' => $item->base_uom_id,
                    'unit_of_measure_code' => $item->baseUom?->uom_code,
                    'stock_quantity' => $stockQuantity,
                    'ledger_quantity' => $ledgerQuantity,
                    'difference' => $difference,
                    'unit_cost' => $unitCost,
                    'repairable' => $repairable,
                    'suggested_remediation' => $repairable
                        ? 'Apply controlled opening inventory repair after confirming this is seed/demo opening stock.'
                        : 'Review manually. Do not auto-repair because ledger history, unit cost, or location context is ambiguous.',
                ];
            })
            ->filter(fn (array $finding): bool => ! DecimalMath::isZero($finding['difference']))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function manifestFindings(): array
    {
        return collect($this->manifestLines())
            ->filter(fn (array $line): bool => $this->lineMatchesOptions($line))
            ->map(fn (array $line): array => $this->buildManifestFinding($line))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function buildManifestFinding(array $line): array
    {
        $item = Item::query()
            ->with(['baseUom'])
            ->where('item_code', $line['item_code'])
            ->first();
        $location = Location::query()
            ->where('code', $line['location_code'])
            ->first();
        $oldCache = DecimalMath::quantity($item?->inventory ?? 0);
        $ledgerQuantity = $item
            ? DecimalMath::quantity(ItemLedgerEntry::query()->where('item_id', $item->id)->sum('quantity'))
            : DecimalMath::quantity(0);
        $approvedQuantity = DecimalMath::quantity($line['approved_quantity']);
        $difference = DecimalMath::sub($approvedQuantity, $oldCache, DecimalPrecision::QUANTITY_SCALE);
        $sourceQuantity = isset($line['source_quantity'])
            ? DecimalMath::quantity($line['source_quantity'])
            : null;
        $conversionFactor = isset($line['conversion_factor'])
            ? DecimalMath::conversion($line['conversion_factor'])
            : DecimalMath::conversion(1);
        $approvedBaseUnitCost = $this->approvedBaseUnitCost($line, $item);
        $openingValue = $approvedBaseUnitCost
            ? DecimalMath::amount(DecimalMath::mul($approvedQuantity, $approvedBaseUnitCost, DecimalPrecision::AMOUNT_SCALE))
            : DecimalMath::amount(0);
        $inventoryPostingSetup = ($item && $location)
            ? InventoryPostingSetup::getFor((int) $item->inventory_posting_group_id, (int) $location->id)
            : null;
        $openingEquityAccount = ChartOfAccount::query()
            ->where('account_number', '30100')
            ->where('direct_posting', true)
            ->first();
        $sourceUnit = isset($line['source_uom'])
            ? UnitOfMeasure::query()->where('uom_code', $line['source_uom'])->first()
            : null;

        $reasonCodes = [];
        if (! $item) {
            $reasonCodes[] = 'missing_item_mapping';
        }
        if (! $location) {
            $reasonCodes[] = 'missing_location';
        }
        if ($item && $item->baseUom?->uom_code !== $line['approved_base_uom']) {
            $reasonCodes[] = 'base_uom_mismatch';
        }
        if (! $approvedBaseUnitCost || ! DecimalMath::isPositive($approvedBaseUnitCost)) {
            $reasonCodes[] = 'missing_or_invalid_approved_base_unit_cost';
        }
        if (! ($line['cost_verified'] ?? false)) {
            $reasonCodes[] = 'cost_not_verified';
        }
        if ($item && ! DecimalMath::isZero($ledgerQuantity)) {
            $reasonCodes[] = 'existing_item_ledger_history';
        }
        if (! $inventoryPostingSetup?->inventoryAccount) {
            $reasonCodes[] = 'missing_inventory_account';
        }
        if (! $openingEquityAccount) {
            $reasonCodes[] = 'missing_opening_equity_account';
        }
        if (isset($line['source_uom']) && ! $sourceUnit) {
            $reasonCodes[] = 'missing_source_uom';
        }
        if ($item && isset($line['source_uom'])) {
            $itemConversionFactor = $item->getConversionFactorForUomDecimal((string) $line['source_uom']);
            if (DecimalMath::compare($itemConversionFactor, $conversionFactor) !== 0) {
                $reasonCodes[] = 'item_uom_conversion_mismatch';
            }
        }
        if (($line['requires_split'] ?? false) === true) {
            $reasonCodes[] = 'item_master_split_required';
        }

        $repairable = $reasonCodes === [];

        return [
            'classification' => 'operator_approved_physical_opening_stock',
            'severity' => $repairable ? 'warning' : 'critical',
            'manifest' => self::PROD_PHYSICAL_MANIFEST,
            'manifest_item_code' => $line['item_code'],
            'item_id' => $item?->id,
            'item_code' => $item?->item_code,
            'item_description' => $item?->description,
            'approved_description' => $line['description'],
            'old_incorrect_cache' => $oldCache,
            'ledger_quantity' => $ledgerQuantity,
            'approved_physical_quantity' => $approvedQuantity,
            'difference' => $difference,
            'approved_base_uom' => $line['approved_base_uom'],
            'source_quantity' => $sourceQuantity,
            'source_uom' => $line['source_uom'] ?? null,
            'conversion_factor' => $conversionFactor,
            'unit_of_measure_id' => $item?->base_uom_id,
            'source_unit_of_measure_id' => $sourceUnit?->id,
            'location_id' => $location?->id,
            'location_code' => $line['location_code'],
            'approved_base_unit_cost' => $approvedBaseUnitCost,
            'current_item_cost' => $item ? DecimalMath::unitCost($item->unit_cost) : null,
            'current_item_base_uom' => $item?->baseUom?->uom_code,
            'opening_value' => $openingValue,
            'cost_source' => $line['cost_source'] ?? 'not supplied',
            'operator_note' => $line['operator_note'] ?? null,
            'inventory_account' => $inventoryPostingSetup?->inventoryAccount?->account_number,
            'opening_equity_account' => $openingEquityAccount?->account_number,
            'repairable' => $repairable,
            'reason_codes' => $reasonCodes,
            'suggested_remediation' => $repairable
                ? 'Apply this manifest line only after confirming backup and operator approval.'
                : $this->manifestRemediation($reasonCodes),
        ];
    }

    /**
     * @param  array<int, string>  $reasonCodes
     */
    private function manifestRemediation(array $reasonCodes): string
    {
        if (in_array('missing_item_mapping', $reasonCodes, true)) {
            return 'Create or map the correct inventory item master before posting this approved quantity.';
        }

        if (in_array('item_master_split_required', $reasonCodes, true)) {
            return 'Split the combined Rubber & Cap item into explicit cap/rubber items before posting cap stock. Do not invent rubber-seal quantity.';
        }

        if (in_array('base_uom_mismatch', $reasonCodes, true)) {
            return 'Correct the item base UOM to the approved base UOM or update the manifest after operator confirmation.';
        }

        if (in_array('missing_or_invalid_approved_base_unit_cost', $reasonCodes, true) || in_array('cost_not_verified', $reasonCodes, true)) {
            return 'Provide a verified base-unit cost before applying the opening correction.';
        }

        if (in_array('existing_item_ledger_history', $reasonCodes, true)) {
            return 'Review manually. The controlled opening correction requires no existing ledger/value history for the affected item.';
        }

        return 'Resolve all reason codes before applying this line.';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function manifestLines(): array
    {
        return [
            [
                'item_code' => 'BOTTLE-60ML',
                'description' => '60 ml Bottle',
                'approved_quantity' => '149184',
                'approved_base_uom' => 'PCS',
                'location_code' => 'GBS-FGN',
                'approved_base_unit_cost' => null,
                'cost_verified' => true,
                'cost_source' => 'prepared item master verified base-unit cost',
                'operator_note' => 'Dedicated bottle item required before apply. Cost must be supplied through item-master preparation.',
            ],
            [
                'item_code' => 'CAP-60ML',
                'description' => 'Bottle Cap',
                'approved_quantity' => '149184',
                'approved_base_uom' => 'PCS',
                'location_code' => 'GBS-FGN',
                'approved_base_unit_cost' => null,
                'cost_verified' => true,
                'cost_source' => 'prepared item master verified base-unit cost',
                'operator_note' => 'Dedicated cap item required. Current seeded item 2500 is combined Rubber & Cap and must not be treated as grams.',
            ],
            [
                'item_code' => '2600',
                'description' => 'Label',
                'approved_quantity' => '149184',
                'approved_base_uom' => 'PCS',
                'location_code' => 'GBS-FGN',
                'approved_base_unit_cost' => '20.00000000',
                'cost_verified' => true,
                'cost_source' => 'current item cost reviewed as base PCS cost',
            ],
            [
                'item_code' => '2800',
                'description' => 'Paper Tray',
                'approved_quantity' => '12432',
                'approved_base_uom' => 'PCS',
                'location_code' => 'GBS-FGN',
                'approved_base_unit_cost' => '70.14000000',
                'cost_verified' => true,
                'cost_source' => 'current item cost reviewed as base PCS cost',
            ],
            [
                'item_code' => '2700',
                'description' => 'Shrink Sleeve',
                'approved_quantity' => '12432',
                'approved_base_uom' => 'PCS',
                'location_code' => 'GBS-FGN',
                'approved_base_unit_cost' => '150.00000000',
                'cost_verified' => true,
                'cost_source' => 'current item cost reviewed as base PCS cost',
            ],
            [
                'item_code' => '2900',
                'description' => 'Mai Sasanci 3-ply Carton Box',
                'approved_quantity' => '518',
                'approved_base_uom' => 'PCS',
                'location_code' => 'GBS-FGN',
                'approved_base_unit_cost' => '723.00000000',
                'cost_verified' => true,
                'cost_source' => 'current item cost reviewed as base PCS cost',
                'operator_note' => 'This is physical carton-box stock, not inferred BOM requirement.',
            ],
            [
                'item_code' => '2100',
                'description' => 'Sodium Saccharine',
                'approved_quantity' => '3400',
                'approved_base_uom' => 'G',
                'location_code' => 'GBS-RAWMAT',
                'approved_base_unit_cost' => '8.80000000',
                'cost_verified' => true,
                'cost_source' => 'current item cost reviewed as base G cost',
            ],
            [
                'item_code' => '2200',
                'description' => 'Ginseng',
                'approved_quantity' => '8840',
                'approved_base_uom' => 'G',
                'location_code' => 'GBS-RAWMAT',
                'approved_base_unit_cost' => '778.50000000',
                'cost_verified' => true,
                'cost_source' => 'current item cost reviewed as base G cost',
            ],
            [
                'item_code' => '2300',
                'description' => 'Yohimbine',
                'approved_quantity' => '20570',
                'approved_base_uom' => 'G',
                'location_code' => 'GBS-RAWMAT',
                'approved_base_unit_cost' => '336.00000000',
                'cost_verified' => true,
                'cost_source' => 'current item cost reviewed as base G cost',
            ],
            [
                'item_code' => '2400',
                'description' => 'Sodium Benzoate',
                'approved_quantity' => '17000',
                'approved_base_uom' => 'G',
                'location_code' => 'GBS-RAWMAT',
                'approved_base_unit_cost' => '2.80000000',
                'cost_verified' => true,
                'cost_source' => 'current item cost reviewed as base G cost',
            ],
            [
                'item_code' => '2410',
                'description' => 'Ficus Carica',
                'approved_quantity' => '75000',
                'approved_base_uom' => 'G',
                'source_quantity' => '3',
                'source_uom' => 'BG',
                'conversion_factor' => '25000',
                'location_code' => 'GBS-RAWMAT',
                'approved_base_unit_cost' => '0.80000000',
                'cost_verified' => true,
                'cost_source' => 'operator note: 20000 per 25kg bag / 25000g',
                'operator_note' => 'Temporary conversion 1 BG = 25000 G. Current item cost 20000 is treated as bag cost, not gram cost.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function approvedBaseUnitCost(array $line, ?Item $item): ?string
    {
        if (isset($line['approved_base_unit_cost'])) {
            return DecimalMath::unitCost($line['approved_base_unit_cost']);
        }

        if (($line['cost_source'] ?? null) !== 'prepared item master verified base-unit cost') {
            return null;
        }

        if (! $item || $item->unit_cost === null || ! DecimalMath::isPositive($item->unit_cost)) {
            return null;
        }

        return DecimalMath::unitCost($item->unit_cost);
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function lineMatchesOptions(array $line): bool
    {
        if ($item = $this->option('item')) {
            if ((string) $line['item_code'] !== (string) $item) {
                return false;
            }
        }

        if ($location = $this->option('location')) {
            if ((string) $line['location_code'] !== (string) $location) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $findings
     */
    private function sumOpeningValue(array $findings): string
    {
        return array_reduce(
            $findings,
            fn (string $carry, array $finding): string => DecimalMath::add($carry, $finding['opening_value'] ?? 0, DecimalPrecision::AMOUNT_SCALE),
            DecimalMath::amount(0),
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
