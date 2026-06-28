<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\ItemLedgerEntry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('biwms:inventory-reconcile {--json : Output machine-readable JSON}')]
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

        $report = [
            'stock_mismatches' => $stockMismatches,
            'negative_stock_violations' => $negativeStockViolations,
            'open_item_ledger_entries' => $openItemLedgerEntries,
            'missing_value_entries' => $missingValueEntries,
            'value_entry_mismatches' => $valueEntryMismatches,
            'missing_item_ledger_entries_for_posted_documents' => $missingLedgerDocuments,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('BIWMS Inventory Reconciliation');
        $this->line('Mode: report-only. No inventory or value entries were changed.');
        $this->newLine();

        $this->section('Item stock field vs item ledger sum mismatches', $stockMismatches, fn (array $item): string => sprintf(
            '%s (%s): stock=%s ledger=%s difference=%s',
            $item['item_code'],
            $item['item_id'],
            number_format($item['stock_quantity'], 4, '.', ''),
            number_format($item['ledger_quantity'], 4, '.', ''),
            number_format($item['difference'], 4, '.', ''),
        ));
        $this->section('Negative stock violations', $negativeStockViolations, fn (array $item): string => sprintf(
            '%s (%s): ledger=%s stock=%s',
            $item['item_code'],
            $item['item_id'],
            number_format($item['ledger_quantity'], 4, '.', ''),
            number_format($item['stock_quantity'], 4, '.', ''),
        ));
        $this->section('Open item ledger entries', $openItemLedgerEntries, fn (array $entry): string => sprintf(
            '#%s %s %s qty=%s remaining=%s',
            $entry['entry_number'],
            $entry['item_code'],
            $entry['document_number'],
            number_format($entry['quantity'], 4, '.', ''),
            number_format($entry['remaining_quantity'], 4, '.', ''),
        ));
        $this->section('Missing value entries', $missingValueEntries, fn (array $entry): string => sprintf(
            '#%s %s %s',
            $entry['entry_number'],
            $entry['item_code'],
            $entry['document_number'],
        ));
        $this->section('Value entry mismatches', $valueEntryMismatches, fn (array $entry): string => sprintf(
            '#%s %s %s: item ledger cost=%s value cost=%s item ledger qty=%s value qty=%s',
            $entry['entry_number'],
            $entry['item_code'],
            $entry['document_number'],
            number_format($entry['item_ledger_cost'], 4, '.', ''),
            number_format($entry['value_entry_cost'], 4, '.', ''),
            number_format($entry['item_ledger_quantity'], 4, '.', ''),
            number_format($entry['value_entry_quantity'], 4, '.', ''),
        ));
        $this->section('Missing item ledger entries for posted inventory documents', $missingLedgerDocuments, fn (array $document): string => sprintf(
            '%s %s line %s item=%s',
            $document['document_type'],
            $document['document_number'],
            $document['line_id'],
            $document['item_id'],
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stockMismatches(): array
    {
        return Item::query()
            ->select('items.id', 'items.item_code', 'items.inventory')
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
                    'stock_quantity' => $stockQuantity,
                    'ledger_quantity' => $ledgerQuantity,
                    'difference' => round($stockQuantity - $ledgerQuantity, 4),
                ];
            })
            ->filter(fn (array $item): bool => abs($item['difference']) > 0.0001)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function negativeStockViolations(): array
    {
        return Item::query()
            ->select('items.id', 'items.item_code', 'items.inventory')
            ->selectSub(
                ItemLedgerEntry::query()
                    ->selectRaw('COALESCE(SUM(quantity), 0)')
                    ->whereColumn('item_ledger_entries.item_id', 'items.id'),
                'ledger_quantity'
            )
            ->orderBy('items.item_code')
            ->get()
            ->map(fn (Item $item): array => [
                'item_id' => $item->id,
                'item_code' => $item->item_code,
                'stock_quantity' => round((float) $item->inventory, 4),
                'ledger_quantity' => round((float) $item->ledger_quantity, 4),
            ])
            ->filter(fn (array $item): bool => $item['ledger_quantity'] < -0.0001)
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
                items.item_code,
                ile.quantity as item_ledger_quantity,
                ve.quantity as value_entry_quantity,
                ile.cost_amount_actual as item_ledger_cost,
                ve.cost_amount_actual as value_entry_cost
            ')
            ->whereRaw('ABS(COALESCE(ile.quantity, 0) - COALESCE(ve.quantity, 0)) > 0.0001')
            ->orWhereRaw('ABS(COALESCE(ile.cost_amount_actual, 0) - COALESCE(ve.cost_amount_actual, 0)) > 0.0001')
            ->orderBy('ile.entry_number')
            ->limit(500)
            ->get()
            ->map(fn ($entry): array => [
                'entry_number' => $entry->entry_number,
                'item_code' => $entry->item_code,
                'document_number' => $entry->document_number,
                'item_ledger_quantity' => round((float) $entry->item_ledger_quantity, 4),
                'value_entry_quantity' => round((float) $entry->value_entry_quantity, 4),
                'item_ledger_cost' => round((float) $entry->item_ledger_cost, 4),
                'value_entry_cost' => round((float) $entry->value_entry_cost, 4),
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
            ->whereNotNull('lines.item_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('item_ledger_entries as ile')
                    ->whereColumn('ile.document_number', 'headers.document_number')
                    ->whereColumn('ile.item_id', 'lines.item_id')
                    ->where('ile.entry_type', 'Sale');
            })
            ->orderBy('headers.document_number')
            ->limit(250)
            ->get([
                'headers.document_number',
                'lines.id as line_id',
                'lines.item_id',
            ])
            ->map(fn ($line): array => [
                'document_type' => 'POSTED_SALES_INVOICE',
                'document_number' => $line->document_number,
                'line_id' => $line->line_id,
                'item_id' => $line->item_id,
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
            ->whereNotNull('lines.item_id')
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
            ])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function section(string $title, array $items, callable $formatter): void
    {
        $count = count($items);
        $this->warn("{$title}: {$count}");

        foreach (array_slice($items, 0, 50) as $item) {
            $this->line(' - '.$formatter($item));
        }

        if ($count > 50) {
            $this->line(' ... '.($count - 50).' more');
        }

        $this->newLine();
    }
}
