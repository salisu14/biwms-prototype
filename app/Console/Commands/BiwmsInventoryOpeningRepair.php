<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Services\Inventory\OpeningInventoryService;
use App\Support\DecimalMath;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('biwms:inventory-opening-repair {--details : Show detailed repair plan rows} {--export= : Write the JSON repair plan to a file path} {--apply : Apply unambiguous non-production opening stock repairs} {--item= : Limit to one item code or id} {--location= : Limit to one location code or id}')]
#[Description('Report and optionally repair cache-only opening inventory mismatches.')]
class BiwmsInventoryOpeningRepair extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $findings = $this->findings();
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findings(): array
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
