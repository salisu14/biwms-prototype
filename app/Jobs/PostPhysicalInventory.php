<?php

// app/Jobs/PostPhysicalInventory.php

namespace App\Jobs;

use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\PhysicalInventoryJournal;
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

class PostPhysicalInventory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public PhysicalInventoryJournal $journal
    ) {}

    public function handle(): void
    {
        DB::transaction(function () {
            // 1. Validate state
            if ($this->journal->status !== 'Calculated') {
                throw new \RuntimeException('Journal must be Calculated before posting.');
            }

            // Only post lines with differences
            $linesToPost = $this->journal->lines()
                ->whereColumn('qty_calculated', '!=', 0)
                ->get();

            if ($linesToPost->isEmpty()) {
                throw new \RuntimeException('No lines with differences to post.');
            }

            $postingDate = $this->journal->posting_date ?? now();
            $entryNo = ItemLedgerEntry::max('entry_no') ?? 0;
            $valueEntryNo = ValueEntry::max('entry_no') ?? 0;

            foreach ($linesToPost as $line) {
                $entryNo++;
                $valueEntryNo++;

                $isPositive = $line->entry_type === 'Positive Adjmt.';
                $qty = abs($line->qty_calculated);
                $sign = $isPositive ? 1 : -1;

                // 2. Create Item Ledger Entry
                $itemLedgerEntry = ItemLedgerEntry::create([
                    'entry_no' => $entryNo,
                    'item_id' => $line->item_id,
                    'posting_date' => $postingDate,
                    'entry_type' => $line->entry_type,
                    'document_no' => $this->journal->journal_batch_name,
                    'document_line_no' => $line->line_no,
                    'description' => 'Phys. Inventory: '.$line->item_description,
                    'location_code' => $line->location_code,
                    'quantity' => $qty * $sign,
                    'remaining_quantity' => $qty * $sign,
                    'invoiced_quantity' => $qty * $sign,
                    'unit_of_measure_code' => $line->unit_of_measure_code,
                    'qty_per_unit_of_measure' => $line->qty_per_unit_of_measure,
                    'quantity_base' => $qty * $sign,
                    'invoiced_qty_base' => $qty * $sign,
                    'unit_cost' => $line->unit_amount,
                    'cost_amount_actual' => $line->amount * $sign,
                    'cost_amount_expected' => 0,
                    'item_tracking_code' => $line->item?->item_tracking_code,
                    'serial_no' => $line->serial_no,
                    'lot_no' => $line->lot_no,
                    'expiration_date' => $line->expiration_date,
                    'open' => true,
                    'positive' => $isPositive,
                    'source_type' => 'Journal',
                    'source_no' => $this->journal->journal_batch_name,
                    'reason_code' => $line->reason_code ?? $this->journal->reason_code,
                    'inventory_posting_group' => $line->inventory_posting_group,
                    'gen_bus_posting_group' => $line->gen_bus_posting_group,
                    'gen_prod_posting_group' => $line->gen_prod_posting_group,
                    'shortcut_dimension_1_code' => $line->shortcut_dimension_1_code,
                    'shortcut_dimension_2_code' => $line->shortcut_dimension_2_code,
                    'dimension_set_id' => $line->dimension_set_id,
                    'qty_to_handle' => $line->qty_to_handle,
                    'qty_to_invoice' => $line->qty_to_invoice,
                    'phys_invt_entry' => true, // Flag for physical inventory entries
                ]);

                // 3. Create Value Entry
                ValueEntry::create([
                    'entry_no' => $valueEntryNo,
                    'item_ledger_entry_no' => $itemLedgerEntry->entry_no,
                    'item_ledger_entry_type' => $line->entry_type,
                    'item_id' => $line->item_id,
                    'posting_date' => $postingDate,
                    'document_no' => $this->journal->journal_batch_name,
                    'document_line_no' => $line->line_no,
                    'description' => 'Phys. Inventory: '.$line->item_description,
                    'location_code' => $line->location_code,
                    'inventory_posting_group' => $line->inventory_posting_group,
                    'gen_bus_posting_group' => $line->gen_bus_posting_group,
                    'gen_prod_posting_group' => $line->gen_prod_posting_group,
                    'source_type' => 'Journal',
                    'source_no' => $this->journal->journal_batch_name,
                    'item_no' => $line->item?->no,
                    'quantity' => $qty * $sign,
                    'invoiced_quantity' => $qty * $sign,
                    'cost_per_unit' => $line->unit_amount,
                    'cost_per_unit_acy' => $line->unit_amount,
                    'sales_amount_actual' => 0,
                    'sales_amount_expected' => 0,
                    'cost_amount_actual' => $line->amount * $sign,
                    'cost_amount_expected' => 0,
                    'cost_amount_non_inv_actual' => 0,
                    'cost_amount_non_inv_expected' => 0,
                    'cost_amount_actual_acy' => $line->amount * $sign,
                    'cost_amount_expected_acy' => 0,
                    'expected_cost_obligatory' => false,
                    'dimension_set_id' => $line->dimension_set_id,
                    'shortcut_dimension_1_code' => $line->shortcut_dimension_1_code,
                    'shortcut_dimension_2_code' => $line->shortcut_dimension_2_code,
                    'entry_type' => 'Direct Cost',
                    'variance_type' => null,
                    'phys_invt_entry' => true,
                ]);

                // 4. Update Item.Inventory
                $item = Item::lockForUpdate()->find($line->item_id);
                if ($item) {
                    $item->increment('inventory', $qty * $sign);
                    $item->increment('inventory_value', $line->amount * $sign);

                    if ($isPositive && $qty > 0) {
                        $newInventory = $item->inventory;
                        $newInventoryValue = $item->inventory_value;
                        if ($newInventory > 0) {
                            $item->unit_cost = $newInventoryValue / $newInventory;
                            $item->save();
                        }
                    }
                }

                // 5. Create Warehouse Entry if WMS enabled
                if (WarehouseSetup::isDirectedPutawayAndPick() && $line->location_code) {
                    WarehouseEntry::create([
                        'reference_no' => $this->journal->journal_batch_name,
                        'reference_line_no' => $line->line_no,
                        'item_id' => $line->item_id,
                        'variant_code' => $line->variant_code,
                        'location_code' => $line->location_code,
                        'bin_code' => $line->bin_code,
                        'zone_code' => null,
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
                        'source_document' => 'Physical Inventory',
                        'whse_document_no' => null,
                        'whse_document_line_no' => null,
                        'entry_type' => $isPositive ? 'Positive Adjmt.' : 'Negative Adjmt.',
                        'registering_date' => $postingDate,
                        'user_id' => auth()->id(),
                    ]);
                }

                // 6. Update line as posted
                $line->update([
                    'qty_handled' => $line->qty_to_handle,
                    'qty_invoiced' => $line->qty_to_invoice,
                ]);
            }

            // 7. Mark journal as posted
            $this->journal->update([
                'status' => 'Posted',
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            Log::info('Physical Inventory posted', [
                'journal' => $this->journal->journal_batch_name,
                'lines_posted' => $linesToPost->count(),
                'entry_no_from' => $entryNo - $linesToPost->count() + 1,
                'entry_no_to' => $entryNo,
            ]);
        });
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Physical Inventory posting failed', [
            'journal' => $this->journal->journal_batch_name,
            'error' => $exception->getMessage(),
        ]);

        $this->journal->update(['status' => 'Calculated']);
    }
}
