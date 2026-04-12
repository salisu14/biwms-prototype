<?php

declare(strict_types=1);

namespace App\Services\Posting;

use App\Enums\JournalLineType;
use App\Models\BinContent;
use App\Models\ItemJournalLine;
use App\Models\ItemLedgerEntry;
use App\Models\ValueEntry;
use App\Models\WarehouseEntry;
use App\Services\Inventory\CostingService;
//use App\Services\Warehouse\WarehousePostingService;
use Illuminate\Support\Facades\Auth;

class ItemJournalPostingRoutine extends AbstractJournalPostingRoutine
{
    public function __construct(
        private readonly CostingService $costingService,
//        private readonly WarehousePostingService $warehousePostingService
    ) {}

    /**
     * @param  ItemJournalLine  $line
     */
    protected function validateLine(object $line): void
    {
        $template = $line->batch->template;

        // Item tracking validation
        if ($template->requiresItemTracking()) {
            if ($template->lot_mandatory && ! $line->lot_no) {
                $this->errors[] = "Line {$line->line_no}: Lot No. is mandatory";
            }
            if ($template->serial_no_mandatory && ! $line->serial_no) {
                $this->errors[] = "Line {$line->line_no}: Serial No. is mandatory";
            }
        }

        // Warehouse validation
        if ($template->warehouse_location_mandatory) {
            if (! $line->location_id) {
                $this->errors[] = "Line {$line->line_no}: Location is mandatory";
            }
            if ($template->bin_mandatory && ! $line->bin_id) {
                $this->errors[] = "Line {$line->line_no}: Bin is mandatory";
            }
        }

        // Negative inventory check
        if (! $template->allow_negative_inventory && $line->isOutbound()) {
            $availableQty = $this->getAvailableQuantity($line);
            if ($availableQty < $line->quantity) {
                $this->errors[] = "Line {$line->line_no}: Insufficient inventory. Available: {$availableQty}, Required: {$line->quantity}";
            }
        }

        // Transfer validation
        if ($line->isTransfer() && ! $line->new_location_id) {
            $this->errors[] = "Line {$line->line_no}: Transfer requires destination location";
        }
    }

    /**
     * @param  ItemJournalLine  $line
     */
    protected function postLine(object $line): void
    {
        $template = $line->batch->template;

        // Determine costs
        $unitCost = $this->determineUnitCost($line);
        $totalCost = $unitCost * $line->quantity;

        // Create Item Ledger Entry
        $itemLedgerEntry = ItemLedgerEntry::create([
            'item_id' => $line->item_id,
            'posting_date' => $line->posting_date,
            'entry_type' => $this->mapEntryType($line->entry_type),
            'document_type' => $line->document_type,
            'document_no' => $line->document_no,
            'document_line_no' => $line->line_no,
            'description' => $line->description,
            'location_id' => $line->location_id,
            'quantity' => $line->isInbound() ? $line->quantity : -$line->quantity,
            'quantity_base' => $line->isInbound() ? $line->quantity_base : -$line->quantity_base,
            'unit_of_measure_code' => $line->unit_of_measure_code,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'lot_no' => $line->lot_no,
            'serial_no' => $line->serial_no,
            'expiration_date' => $line->expiration_date,
            'source_code' => $line->source_code ?? $template->source_code,
            'reason_code' => $line->reason_code,
        ]);

        // Create Value Entry (detailed cost)
        $valueEntry = ValueEntry::create([
            'item_ledger_entry_id' => $itemLedgerEntry->id,
            'item_id' => $line->item_id,
            'posting_date' => $line->posting_date,
            'entry_type' => $itemLedgerEntry->entry_type,
            'document_no' => $line->document_no,
            'quantity' => $itemLedgerEntry->quantity,
            'unit_cost' => $unitCost,
            'cost_per_unit' => $unitCost,
            'cost_amount_actual' => $totalCost,
            'cost_amount_expected' => 0, // Adjust later if needed
            'invoiced_quantity' => $itemLedgerEntry->quantity,
        ]);

        // Create Warehouse Entry if location uses bins
        if ($line->bin_id || $line->zone_id) {
            $this->createWarehouseEntry($line, $itemLedgerEntry);
        }

        // Update Bin Content
        $this->updateBinContent($line);

        // Create GL Entries for inventory impact
        $this->createInventoryGLEntries($line, $totalCost);

        $this->updateLineStatus($line, 'posted', $itemLedgerEntry->id, ItemLedgerEntry::class);
    }

    private function determineUnitCost(ItemJournalLine $line): float
    {
        if ($line->unit_amount) {
            return (float) $line->unit_amount;
        }

        if ($line->isOutbound()) {
            // Get cost from inventory valuation
            return $this->costingService->getUnitCost(
                item: $line->item,
                location: $line->location,
                lotNo: $line->lot_no,
                asOfDate: $line->posting_date
            );
        }

        return 0; // Inbound without specified cost
    }

    private function createWarehouseEntry(ItemJournalLine $line, ItemLedgerEntry $itemLedgerEntry): void
    {
        $entryType = $line->isInbound() ? 'positive' : ($line->isOutbound() ? 'negative' : 'transfer');

        WarehouseEntry::create([
            'item_id' => $line->item_id,
            'location_id' => $line->location_id,
            'zone_id' => $line->zone_id,
            'bin_id' => $line->bin_id,
            'lot_no' => $line->lot_no,
            'serial_no' => $line->serial_no,
            'entry_type' => $entryType,
            'quantity' => abs($line->quantity),
            'quantity_base' => abs($line->quantity_base),
            'unit_of_measure_code' => $line->unit_of_measure_code,
            'document_type' => 'item_journal',
            'document_no' => $line->document_no,
            'document_line_no' => $line->line_no,
            'item_ledger_entry_id' => $itemLedgerEntry->id,
            'entry_timestamp' => now(),
            'created_by' => Auth::id(),
        ]);

        // Handle transfer destination
        if ($line->isTransfer() && $line->new_bin_id) {
            WarehouseEntry::create([
                'item_id' => $line->item_id,
                'location_id' => $line->new_location_id,
                'zone_id' => $line->new_zone_id,
                'bin_id' => $line->new_bin_id,
                'lot_no' => $line->new_lot_no ?? $line->lot_no,
                'entry_type' => 'positive',
                'quantity' => abs($line->quantity),
                'quantity_base' => abs($line->quantity_base),
                'unit_of_measure_code' => $line->unit_of_measure_code,
                'document_type' => 'item_journal',
                'document_no' => $line->document_no,
                'document_line_no' => $line->line_no,
                'item_ledger_entry_id' => $itemLedgerEntry->id,
                'entry_timestamp' => now(),
                'created_by' => Auth::id(),
            ]);
        }
    }

    private function updateBinContent(ItemJournalLine $line): void
    {
        if (! $line->bin_id) {
            return;
        }

        $content = BinContent::firstOrNew([
            'bin_id' => $line->bin_id,
            'item_id' => $line->item_id,
            'lot_no' => $line->lot_no,
            'serial_no' => $line->serial_no,
        ]);

        $delta = $line->isInbound() ? $line->quantity : -$line->quantity;
        $content->quantity += $delta;

        if ($content->quantity <= 0) {
            $content->delete();
        } else {
            $content->save();
        }
    }

    private function createInventoryGLEntries(ItemJournalLine $line, float $totalCost): void
    {
        // Implementation depends on your Inventory Posting Setup
        // Debit/Credit Inventory Account vs. Offset Account
    }

    private function getAvailableQuantity(ItemJournalLine $line): float
    {
        // Query BinContent or Item availability
        return 0; // Placeholder
    }

    private function mapEntryType(JournalLineType $type): string
    {
        return match ($type) {
            JournalLineType::POSITIVE_ADJUSTMENT => 'positive_adj',
            JournalLineType::NEGATIVE_ADJUSTMENT => 'negative_adj',
            JournalLineType::PURCHASE => 'purchase',
            JournalLineType::SALE => 'sale',
            JournalLineType::TRANSFER => 'transfer',
            JournalLineType::CONSUMPTION => 'consumption',
            JournalLineType::OUTPUT => 'output',
            default => 'adjustment',
        };
    }
}
