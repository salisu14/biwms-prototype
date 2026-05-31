<?php

// app/Jobs/PostInventoryAdjustment.php

namespace App\Jobs;

use App\Models\InventoryAdjustmentJournal;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\ValueEntry;
use App\Models\WarehouseEntry;
use App\Models\WarehouseSetup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PostInventoryAdjustment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public InventoryAdjustmentJournal $journal
    ) {}

    public function handle(): void
    {
        DB::transaction(function () {
            // 1. Validate state
            if ($this->journal->status !== 'Released') {
                throw new \RuntimeException('Journal must be Released before posting.');
            }

            if ($this->journal->lines()->count() === 0) {
                throw new \RuntimeException('Journal has no lines to post.');
            }

            $postingDate = $this->journal->posting_date ?? now();
            $entryNo = ItemLedgerEntry::max('entry_number') ?? 0;
            $valueEntryNo = ValueEntry::max('entry_no') ?? 0;

            foreach ($this->journal->lines as $line) {
                $entryNo++;
                $valueEntryNo++;

                // 2. Determine sign based on entry type
                $isPositive = $line->entry_type === 'Positive Adjmt.';
                $qty = abs($line->quantity_base);
                $sign = $isPositive ? 1 : -1;

                // 3. Create Item Ledger Entry (BC ILE)
                $itemLedgerEntry = ItemLedgerEntry::create([
                    'entry_number' => $entryNo,
                    'item_id' => $line->item_id,
                    'posting_date' => $postingDate,
                    'entry_type' => $line->entry_type,
                    'document_number' => $this->journal->journal_batch_name,
                    'document_line_number' => $line->line_no,
                    'location_id' => $line->location_id,
                    'quantity' => $qty * $sign,
                    'remaining_quantity' => $qty * $sign,
                    'cost_amount_actual' => $line->amount * $sign,
                    'cost_amount_expected' => 0,
                    'serial_number' => $line->serial_no,
                    'lot_number' => $line->lot_no,
                    'expiration_date' => $line->expiration_date,
                    'open' => true,
                    'source_type' => 'Journal',
                    'source_id' => $this->journal->id,
                    'entry_date' => now(),
                ]);

                // 4. Create Value Entry (BC VE)
                ValueEntry::create([
                    'entry_no' => $valueEntryNo,
                    'item_ledger_entry_no' => $itemLedgerEntry->entry_number,
                    'item_ledger_entry_type' => $this->mapValueEntryItemLedgerType($line->entry_type),
                    'posting_date' => $postingDate,
                    'document_no' => $this->journal->journal_batch_name,
                    'document_line_no' => $line->line_no,
                    'description' => $line->description,
                    'location_code' => (string) ($line->location_code ?? $line->location?->code ?? 'MAIN'),
                    'source_type' => 'Journal',
                    'source_no' => $this->journal->journal_batch_name,
                    'item_no' => (string) ($line->item?->item_code ?? $line->item_id),
                    'quantity' => $qty * $sign,
                    'invoiced_quantity' => $qty * $sign,
                    'unit_cost' => (float) $line->unit_cost,
                    'unit_cost_acy' => (float) $line->unit_cost,
                    'cost_amount_actual' => $line->amount * $sign,
                    'cost_amount_expected' => 0,
                    'cost_amount_actual_acy' => $line->amount * $sign,
                    'cost_amount_expected_acy' => 0,
                    'entry_type' => 'Direct Cost',
                ]);

                // 5. Update Item.Inventory (BC Item table)
                $item = Item::lockForUpdate()->find($line->item_id);
                if ($item) {
                    $item->increment('inventory', $qty * $sign);
                    $item->increment('inventory_value', $line->amount * $sign);

                    // Update unit cost if positive adjustment and cost changed
                    if ($isPositive && $qty > 0) {
                        $newInventory = $item->inventory;
                        $newInventoryValue = $item->inventory_value;
                        if ($newInventory > 0) {
                            $item->unit_cost = $newInventoryValue / $newInventory;
                            $item->save();
                        }
                    }
                }

                // 6. Create Warehouse Entry if WMS enabled and location/bin present
                if (WarehouseSetup::isDirectedPutawayAndPick() && $line->location_code) {
                    WarehouseEntry::create([
                        'reference_no' => $this->journal->journal_batch_name,
                        'reference_line_no' => $line->line_no,
                        'item_id' => $line->item_id,
                        'variant_code' => $line->variant_code,
                        'location_code' => $line->location_code,
                        'bin_code' => $line->bin_code,
                        'zone_code' => null, // Resolve from bin if needed
                        'quantity' => $qty * $sign,
                        'qty_base' => $qty * $sign,
                        'qty_outstanding' => $qty * $sign,
                        'unit_of_measure_code' => $line->unit_of_measure_code,
                        'qty_per_unit_of_measure' => $line->qty_per_unit_of_measure,
                        'serial_no' => $line->serial_no,
                        'lot_no' => $line->lot_no,
                        'expiration_date' => $line->expiration_date,
                        'source_type' => 'Journal',
                        'source_subtype' => $line->entry_type,
                        'source_no' => $this->journal->journal_batch_name,
                        'source_line_no' => $line->line_no,
                        'source_document' => 'Inventory Adjustment',
                        'whse_document_no' => null,
                        'whse_document_line_no' => null,
                        'entry_type' => $isPositive ? 'Positive Adjmt.' : 'Negative Adjmt.',
                        'registering_date' => $postingDate,
                        'user_id' => auth()->id(),
                    ]);
                }

                // 7. Update line status
                $line->update([
                    'qty_handled' => $line->quantity_to_handle,
                    'qty_invoiced' => $line->quantity_to_invoice,
                ]);
            }

            // 8. Mark journal as posted
            $this->journal->update([
                'status' => 'Posted',
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            Log::info('Inventory Adjustment posted', [
                'journal' => $this->journal->journal_batch_name,
                'lines' => $this->journal->lines()->count(),
                'entry_no_from' => $entryNo - $this->journal->lines()->count() + 1,
                'entry_no_to' => $entryNo,
            ]);
        });
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Inventory Adjustment posting failed', [
            'journal' => $this->journal->journal_batch_name,
            'error' => $exception->getMessage(),
        ]);

        // Optionally notify user or revert status
        $this->journal->update(['status' => 'Released']);
    }

    private function mapValueEntryItemLedgerType(string $entryType): int
    {
        return match (strtolower($entryType)) {
            'purchase' => 1,
            'sale' => 2,
            'positive_adj', 'positive adjustment', 'positive adjmt.' => 3,
            'negative_adj', 'negative adjustment', 'negative adjmt.' => 4,
            'transfer' => 5,
            'consumption' => 6,
            'output' => 7,
            default => 0,
        };
    }
}
