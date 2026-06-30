<?php

namespace App\Console\Commands;

use App\Enums\ItemType;
use App\Enums\ProductionOrderStatus;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Manufacturing\ProductionOrder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

#[Signature('biwms:inventory-reconcile {--json : Output machine-readable JSON} {--details : Show detailed diagnostic rows} {--export= : Write the JSON report to a file path}')]
#[Description('Report BIWMS inventory ledger, value entry, and cached stock consistency issues.')]
class BiwmsInventoryReconcile extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $stockMismatches = $this->stockMismatches();
        $negativeStockViolations = $this->negativeStockViolations();
        $openItemLedgerEntries = $this->openItemLedgerEntries();
        $missingValueEntries = $this->missingValueEntries();
        $valueEntryMismatches = $this->valueEntryMismatches();
        $missingLedgerDocuments = $this->missingLedgerDocuments();
        $productionOutputWithoutConsumption = $this->productionOutputWithoutConsumption();
        $productionConsumptionWithoutValueEntries = $this->productionLedgerEntriesWithoutValueEntries('Consumption');
        $productionOutputWithoutValueEntries = $this->productionLedgerEntriesWithoutValueEntries('Output');
        $finishedProductionOrdersWithOpenWip = $this->finishedProductionOrdersWithOpenWip();

        $report = [
            'stock_mismatches' => $stockMismatches,
            'negative_stock_violations' => $negativeStockViolations,
            'open_item_ledger_entries' => $openItemLedgerEntries,
            'missing_value_entries' => $missingValueEntries,
            'value_entry_mismatches' => $valueEntryMismatches,
            'missing_item_ledger_entries_for_posted_documents' => $missingLedgerDocuments,
            'production_orders_with_output_without_consumption' => $productionOutputWithoutConsumption,
            'production_consumption_without_value_entries' => $productionConsumptionWithoutValueEntries,
            'production_output_without_value_entries' => $productionOutputWithoutValueEntries,
            'finished_production_orders_with_open_wip' => $finishedProductionOrdersWithOpenWip,
        ];

        if ($exportPath = $this->option('export')) {
            $this->exportReport($report, (string) $exportPath);
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('BIWMS Inventory Reconciliation');
        $this->line('Mode: report-only. No inventory or value entries were changed.');
        if ($exportPath) {
            $this->line("Exported JSON report to {$exportPath}.");
        }
        $this->newLine();

        $details = (bool) $this->option('details');

        $this->section('Item stock field vs item ledger sum mismatches', $stockMismatches, $details, fn (array $item): string => sprintf(
            '[%s] %s (%s): stock=%s ledger=%s difference=%s',
            $item['severity'],
            $item['item_code'],
            $item['item_id'],
            number_format($item['stock_quantity'], 4, '.', ''),
            number_format($item['ledger_quantity'], 4, '.', ''),
            number_format($item['difference'], 4, '.', ''),
        ));
        $this->section('Negative stock violations', $negativeStockViolations, $details, fn (array $item): string => sprintf(
            '[%s] %s (%s) location=%s lot=%s serial=%s: ledger=%s stock=%s',
            $item['severity'],
            $item['item_code'],
            $item['item_id'],
            $item['location_code'] ?? 'ALL',
            $item['lot_number'] ?? 'N/A',
            $item['serial_number'] ?? 'N/A',
            number_format($item['ledger_quantity'], 4, '.', ''),
            number_format($item['stock_quantity'], 4, '.', ''),
        ));
        $this->section('Open item ledger entries', $openItemLedgerEntries, $details, fn (array $entry): string => sprintf(
            '[%s] #%s %s %s qty=%s remaining=%s',
            $entry['severity'],
            $entry['entry_number'],
            $entry['item_code'],
            $entry['document_number'],
            number_format($entry['quantity'], 4, '.', ''),
            number_format($entry['remaining_quantity'], 4, '.', ''),
        ));
        $this->section('Missing value entries', $missingValueEntries, $details, fn (array $entry): string => sprintf(
            '[%s] #%s %s %s',
            $entry['severity'],
            $entry['entry_number'],
            $entry['item_code'],
            $entry['document_number'],
        ));
        $this->section('Value entry mismatches', $valueEntryMismatches, $details, fn (array $entry): string => sprintf(
            '[%s] #%s value #%s %s %s: item ledger cost=%s value cost=%s item ledger qty=%s value qty=%s',
            $entry['severity'],
            $entry['entry_number'],
            $entry['value_entry_no'],
            $entry['item_code'],
            $entry['document_number'],
            number_format($entry['item_ledger_cost'], 4, '.', ''),
            number_format($entry['value_entry_cost'], 4, '.', ''),
            number_format($entry['item_ledger_quantity'], 4, '.', ''),
            number_format($entry['value_entry_quantity'], 4, '.', ''),
        ));
        $this->section('Missing item ledger entries for posted inventory documents', $missingLedgerDocuments, $details, fn (array $document): string => sprintf(
            '[%s] %s %s line %s item=%s linked_entry=%s',
            $document['severity'],
            $document['document_type'],
            $document['document_number'],
            $document['line_id'],
            $document['item_id'],
            $document['item_ledger_entry_id'] ?? 'N/A',
        ));
        $this->section('Production orders with output but no consumption', $productionOutputWithoutConsumption, $details, fn (array $order): string => sprintf(
            '[%s] %s output_qty=%s output_cost=%s',
            $order['severity'],
            $order['document_number'],
            number_format($order['output_quantity'], 4, '.', ''),
            number_format($order['output_cost'], 4, '.', ''),
        ));
        $this->section('Production consumption entries without value entries', $productionConsumptionWithoutValueEntries, $details, fn (array $entry): string => sprintf(
            '[%s] #%s %s %s qty=%s cost=%s',
            $entry['severity'],
            $entry['entry_number'],
            $entry['item_code'],
            $entry['document_number'],
            number_format($entry['quantity'], 4, '.', ''),
            number_format($entry['cost_amount_actual'], 4, '.', ''),
        ));
        $this->section('Production output entries without value entries', $productionOutputWithoutValueEntries, $details, fn (array $entry): string => sprintf(
            '[%s] #%s %s %s qty=%s cost=%s',
            $entry['severity'],
            $entry['entry_number'],
            $entry['item_code'],
            $entry['document_number'],
            number_format($entry['quantity'], 4, '.', ''),
            number_format($entry['cost_amount_actual'], 4, '.', ''),
        ));
        $this->section('Finished production orders with unexpected open WIP', $finishedProductionOrdersWithOpenWip, $details, fn (array $order): string => sprintf(
            '[%s] %s wip_net=%s',
            $order['severity'],
            $order['document_number'],
            number_format($order['wip_net_amount'], 4, '.', ''),
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stockMismatches(): array
    {
        return Item::query()
            ->select('items.id', 'items.item_code', 'items.description', 'items.inventory')
            ->selectSub(
                ItemLedgerEntry::query()
                    ->selectRaw('COALESCE(SUM(quantity), 0)')
                    ->whereColumn('item_ledger_entries.item_id', 'items.id'),
                'ledger_quantity'
            )
            ->orderBy('items.item_code')
            ->get()
            ->map(function (Item $item): array {
                $stockQuantity = round((float) $item->inventory, 4);
                $ledgerQuantity = round((float) $item->ledger_quantity, 4);

                return [
                    'item_id' => $item->id,
                    'item_code' => $item->item_code,
                    'description' => $item->description,
                    'stock_quantity' => $stockQuantity,
                    'ledger_quantity' => $ledgerQuantity,
                    'difference' => round($stockQuantity - $ledgerQuantity, 4),
                    ...$this->findingMetadata(
                        classification: 'stock_cache_mismatch',
                        severity: 'warning',
                        suggestedRemediation: 'Review the item ledger total against the cached item inventory field. After confirming posting logic is fixed, prepare a reviewed cache correction or inventory adjustment; do not edit ledger history directly.'
                    ),
                ];
            })
            ->filter(fn (array $item): bool => abs($item['difference']) > 0.0001)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function productionOutputWithoutConsumption(): array
    {
        if (! DB::getSchemaBuilder()->hasTable('production_orders')) {
            return [];
        }

        return DB::table('production_orders as po')
            ->join('item_ledger_entries as output_entries', function ($join): void {
                $join->on('output_entries.source_id', '=', 'po.id')
                    ->where('output_entries.source_type', ProductionOrder::class)
                    ->where('output_entries.entry_type', 'Output');
            })
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('item_ledger_entries as consumption_entries')
                    ->whereColumn('consumption_entries.source_id', 'po.id')
                    ->where('consumption_entries.source_type', ProductionOrder::class)
                    ->where('consumption_entries.entry_type', 'Consumption');
            })
            ->groupBy('po.id', 'po.document_number')
            ->orderBy('po.document_number')
            ->limit(250)
            ->get([
                'po.id as production_order_id',
                'po.document_number',
                DB::raw('COALESCE(SUM(output_entries.quantity), 0) as output_quantity'),
                DB::raw('COALESCE(SUM(output_entries.cost_amount_actual), 0) as output_cost'),
            ])
            ->map(fn ($order): array => [
                'production_order_id' => $order->production_order_id,
                'document_number' => $order->document_number,
                'output_quantity' => round((float) $order->output_quantity, 4),
                'output_cost' => round((float) $order->output_cost, 4),
                ...$this->findingMetadata(
                    classification: 'production_output_without_consumption',
                    severity: 'critical',
                    suggestedRemediation: 'Review the production order routing/flushing method. Post or reconstruct approved component consumption before accepting output cost, or reverse the unsupported output.'
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function productionLedgerEntriesWithoutValueEntries(string $entryType): array
    {
        return DB::table('item_ledger_entries as ile')
            ->join('items', 'items.id', '=', 'ile.item_id')
            ->where('ile.source_type', ProductionOrder::class)
            ->where('ile.entry_type', $entryType)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('value_entries as ve')
                    ->whereColumn('ve.item_ledger_entry_no', 'ile.entry_number');
            })
            ->orderBy('ile.entry_number')
            ->limit(250)
            ->get([
                'ile.entry_number',
                'ile.document_number',
                'ile.document_line_number',
                'ile.item_id',
                'items.item_code',
                'ile.quantity',
                'ile.cost_amount_actual',
            ])
            ->map(fn ($entry): array => [
                'entry_number' => $entry->entry_number,
                'document_number' => $entry->document_number,
                'document_line_number' => $entry->document_line_number,
                'item_id' => $entry->item_id,
                'item_code' => $entry->item_code,
                'quantity' => round((float) $entry->quantity, 4),
                'cost_amount_actual' => round((float) $entry->cost_amount_actual, 4),
                ...$this->findingMetadata(
                    classification: strtolower("production_{$entryType}_without_value_entry"),
                    severity: 'critical',
                    suggestedRemediation: 'Regenerate or manually create the missing Value Entry only after validating the Item Ledger Entry quantity, cost, posting date, and production order source.'
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function finishedProductionOrdersWithOpenWip(): array
    {
        if (! DB::getSchemaBuilder()->hasTable('production_orders')) {
            return [];
        }

        return DB::table('production_orders as po')
            ->leftJoin('locations', 'locations.code', '=', 'po.location_code')
            ->join('inventory_posting_setups as ips', function ($join): void {
                $join->on('ips.inventory_posting_group_id', '=', 'po.inventory_posting_group_id')
                    ->where(function ($query): void {
                        $query
                            ->whereColumn('ips.location_id', 'locations.id')
                            ->orWhereNull('ips.location_id');
                    });
            })
            ->join('gl_entries as gl', function ($join): void {
                $join->on('gl.document_number', '=', 'po.document_number')
                    ->on('gl.chart_of_account_id', '=', 'ips.wip_account_id');
            })
            ->where('po.status', ProductionOrderStatus::FINISHED->value)
            ->whereNotNull('ips.wip_account_id')
            ->groupBy('po.id', 'po.document_number')
            ->havingRaw('ABS(COALESCE(SUM(gl.debit_amount), 0) - COALESCE(SUM(gl.credit_amount), 0)) > 0.01')
            ->orderBy('po.document_number')
            ->limit(250)
            ->get([
                'po.id as production_order_id',
                'po.document_number',
                DB::raw('COALESCE(SUM(gl.debit_amount), 0) - COALESCE(SUM(gl.credit_amount), 0) as wip_net_amount'),
            ])
            ->map(fn ($order): array => [
                'production_order_id' => $order->production_order_id,
                'document_number' => $order->document_number,
                'wip_net_amount' => round((float) $order->wip_net_amount, 4),
                ...$this->findingMetadata(
                    classification: 'finished_production_order_open_wip',
                    severity: 'critical',
                    suggestedRemediation: 'Review finish posting, WIP clearing, and variance entries. Post an approved variance or correction only after confirming finished goods value and WIP ledger balance.'
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function negativeStockViolations(): array
    {
        return DB::table('item_ledger_entries as ile')
            ->join('items', 'items.id', '=', 'ile.item_id')
            ->leftJoin('locations', 'locations.id', '=', 'ile.location_id')
            ->selectRaw('
                ile.item_id,
                items.item_code,
                items.description,
                items.inventory as stock_quantity,
                ile.location_id,
                locations.code as location_code,
                ile.lot_number,
                ile.serial_number,
                COALESCE(SUM(ile.quantity), 0) as ledger_quantity
            ')
            ->groupBy(
                'ile.item_id',
                'items.item_code',
                'items.description',
                'items.inventory',
                'ile.location_id',
                'locations.code',
                'ile.lot_number',
                'ile.serial_number',
            )
            ->havingRaw('COALESCE(SUM(ile.quantity), 0) < -0.0001')
            ->orderBy('items.item_code')
            ->get()
            ->map(fn ($item): array => [
                'item_id' => $item->item_id,
                'item_code' => $item->item_code,
                'description' => $item->description,
                'stock_quantity' => round((float) $item->stock_quantity, 4),
                'ledger_quantity' => round((float) $item->ledger_quantity, 4),
                'location_id' => $item->location_id,
                'location_code' => $item->location_code,
                'lot_number' => $item->lot_number,
                'serial_number' => $item->serial_number,
                ...$this->findingMetadata(
                    classification: 'negative_stock',
                    severity: 'critical',
                    suggestedRemediation: 'Trace the outbound posting for this item/location/tracking context, confirm whether stock should have existed, then correct through an approved inventory adjustment or reversal path.'
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function openItemLedgerEntries(): array
    {
        return ItemLedgerEntry::query()
            ->with('item:id,item_code')
            ->where('open', true)
            ->where('remaining_quantity', '!=', 0)
            ->orderBy('entry_number')
            ->limit(500)
            ->get()
            ->map(fn (ItemLedgerEntry $entry): array => [
                'entry_number' => $entry->entry_number,
                'item_id' => $entry->item_id,
                'item_code' => $entry->item?->item_code,
                'document_type' => $entry->document_type,
                'document_number' => $entry->document_number,
                'quantity' => round((float) $entry->quantity, 4),
                'remaining_quantity' => round((float) $entry->remaining_quantity, 4),
                ...$this->findingMetadata(
                    classification: 'open_item_ledger_entry',
                    severity: 'info',
                    suggestedRemediation: 'Confirm whether this legacy entry should remain open for application. If not, close it through the appropriate application/costing process after finance review.'
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function missingValueEntries(): array
    {
        return ItemLedgerEntry::query()
            ->with('item:id,item_code')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('value_entries')
                    ->whereColumn('value_entries.item_ledger_entry_no', 'item_ledger_entries.entry_number');
            })
            ->orderBy('entry_number')
            ->limit(500)
            ->get()
            ->map(fn (ItemLedgerEntry $entry): array => [
                'entry_number' => $entry->entry_number,
                'item_id' => $entry->item_id,
                'item_code' => $entry->item?->item_code,
                'document_type' => $entry->document_type,
                'document_number' => $entry->document_number,
                ...$this->findingMetadata(
                    classification: 'value_entry_mismatch',
                    severity: 'critical',
                    suggestedRemediation: 'Create a reviewed manual remediation plan to add the missing Value Entry from the Item Ledger Entry quantity and cost. Do not insert it without finance approval and audit notes.'
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function valueEntryMismatches(): array
    {
        return DB::table('item_ledger_entries as ile')
            ->join('items', 'items.id', '=', 'ile.item_id')
            ->join('value_entries as ve', 've.item_ledger_entry_no', '=', 'ile.entry_number')
            ->selectRaw('
                ile.entry_number,
                ile.document_number,
                ve.entry_no as value_entry_no,
                items.item_code,
                ile.quantity as item_ledger_quantity,
                ve.quantity as value_entry_quantity,
                ile.cost_amount_actual as item_ledger_cost,
                ve.cost_amount_actual as value_entry_cost
            ')
            ->where(function ($query): void {
                $query
                    ->whereRaw('ABS(COALESCE(ile.quantity, 0) - COALESCE(ve.quantity, 0)) > 0.0001')
                    ->orWhereRaw('ABS(COALESCE(ile.cost_amount_actual, 0) - COALESCE(ve.cost_amount_actual, 0)) > 0.0001');
            })
            ->orderBy('ile.entry_number')
            ->limit(500)
            ->get()
            ->map(fn ($entry): array => [
                'entry_number' => $entry->entry_number,
                'value_entry_no' => $entry->value_entry_no,
                'item_code' => $entry->item_code,
                'document_number' => $entry->document_number,
                'item_ledger_quantity' => round((float) $entry->item_ledger_quantity, 4),
                'value_entry_quantity' => round((float) $entry->value_entry_quantity, 4),
                'item_ledger_cost' => round((float) $entry->item_ledger_cost, 4),
                'value_entry_cost' => round((float) $entry->value_entry_cost, 4),
                ...$this->findingMetadata(
                    classification: 'value_entry_mismatch',
                    severity: 'critical',
                    suggestedRemediation: 'Compare the Item Ledger Entry and Value Entry source document, quantity, and cost. Correct only through a reviewed value adjustment or controlled data repair plan.'
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function missingLedgerDocuments(): array
    {
        return collect()
            ->merge($this->missingPostedSalesInvoiceLineEntries())
            ->merge($this->missingPostedPurchaseInvoiceLineEntries())
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function missingPostedSalesInvoiceLineEntries(): array
    {
        if (! DB::getSchemaBuilder()->hasTable('posted_sales_invoice_lines')) {
            return [];
        }

        return DB::table('posted_sales_invoice_lines as lines')
            ->join('posted_sales_invoices as headers', 'headers.id', '=', 'lines.posted_sales_invoice_id')
            ->join('items', 'items.id', '=', 'lines.item_id')
            ->whereNotNull('lines.item_id')
            ->whereIn('items.item_type', ItemType::inventoryTypes())
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('item_ledger_entries as ile')
                    ->whereColumn('ile.id', 'lines.item_ledger_entry_id')
                    ->whereColumn('ile.item_id', 'lines.item_id')
                    ->where('ile.entry_type', 'Sale');
            })
            ->orderBy('headers.document_number')
            ->limit(250)
            ->get([
                'headers.document_number',
                'lines.id as line_id',
                'lines.item_id',
                'lines.item_ledger_entry_id',
            ])
            ->map(fn ($line): array => [
                'document_type' => 'POSTED_SALES_INVOICE',
                'document_number' => $line->document_number,
                'line_id' => $line->line_id,
                'item_id' => $line->item_id,
                'item_ledger_entry_id' => $line->item_ledger_entry_id,
                ...$this->findingMetadata(
                    classification: 'missing_item_ledger_link',
                    severity: 'critical',
                    suggestedRemediation: 'Find the related sales shipment or invoice Item Ledger Entry and validate item, quantity, document, and posting date before linking or creating any reviewed correction.'
                ),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function missingPostedPurchaseInvoiceLineEntries(): array
    {
        if (! DB::getSchemaBuilder()->hasTable('posted_purchase_invoice_lines')) {
            return [];
        }

        return DB::table('posted_purchase_invoice_lines as lines')
            ->join('posted_purchase_invoices as headers', 'headers.id', '=', 'lines.posted_purchase_invoice_id')
            ->join('items', 'items.id', '=', 'lines.item_id')
            ->whereNotNull('lines.item_id')
            ->whereIn('items.item_type', ItemType::inventoryTypes())
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('item_ledger_entries as ile')
                    ->whereColumn('ile.document_number', 'headers.document_number')
                    ->whereColumn('ile.item_id', 'lines.item_id')
                    ->where('ile.entry_type', 'Purchase');
            })
            ->orderBy('headers.document_number')
            ->limit(250)
            ->get([
                'headers.document_number',
                'lines.id as line_id',
                'lines.item_id',
            ])
            ->map(fn ($line): array => [
                'document_type' => 'POSTED_PURCHASE_INVOICE',
                'document_number' => $line->document_number,
                'line_id' => $line->line_id,
                'item_id' => $line->item_id,
                ...$this->findingMetadata(
                    classification: 'missing_item_ledger_link',
                    severity: 'critical',
                    suggestedRemediation: 'Find the related purchase receipt or invoice Item Ledger Entry and validate item, quantity, document, and posting date before linking or creating any reviewed correction.'
                ),
            ])
            ->all();
    }

    /**
     * @return array{classification: string, severity: string, suggested_remediation: string}
     */
    private function findingMetadata(string $classification, string $severity, string $suggestedRemediation): array
    {
        return [
            'classification' => $classification,
            'severity' => $severity,
            'suggested_remediation' => $suggestedRemediation,
        ];
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $report
     */
    private function exportReport(array $report, string $path): void
    {
        $absolutePath = str_starts_with($path, DIRECTORY_SEPARATOR)
            ? $path
            : base_path($path);

        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function section(string $title, array $items, bool $details, callable $formatter): void
    {
        $count = count($items);
        $this->warn("{$title}: {$count}");

        if (! $details) {
            if ($count > 0) {
                $this->line('   Run with --details to show rows.');
            }

            $this->newLine();

            return;
        }

        foreach (array_slice($items, 0, 50) as $item) {
            $this->line(' - '.$formatter($item));
        }

        if ($count > 50) {
            $this->line(' ... '.($count - 50).' more');
        }

        $this->newLine();
    }
}
