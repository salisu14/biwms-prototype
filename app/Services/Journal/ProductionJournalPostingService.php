<?php

namespace App\Services\Journal;

use App\Models\ProductionJournalLine;
use App\Models\ItemLedgerEntry;
use App\Models\CapacityLedgerEntry;
use App\Enums\FlushingMethod;
use App\Enums\JournalLineStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ProductionJournalPostingService
{
    /**
     * Post a single production journal line.
     * @throws \Throwable
     */
    public function post(ProductionJournalLine $line): bool
    {
        // 1. Check if already posted or flushed
        if ($line->line_status === JournalLineStatus::POSTED || $line->flushed) {
            return false;
        }

        return DB::transaction(function () use ($line) {
            // 2. Post Item Ledger (Inventory Impact)
            // Logic: Consumption reduces stock (-), Output increases stock (+)
            $this->postItemLedger($line);

            // 3. Post Capacity Ledger (Time & Cost Impact)
            // Only if setup_time, run_time, or output_quantity (for piecework) exists
            if ($line->getTotalTime() > 0 || (float) $line->output_quantity > 0) {
                $this->postCapacityLedger($line);
            }

            // 4. Update the Journal Line Status
            $line->update([
                'line_status' => JournalLineStatus::POSTED,
                'flushed' => true,
                'flushed_at' => now(),
                'created_by' => Auth::id() ?? $line->created_by,
            ]);

            return true;
        });
    }

    /**
     * Handles physical inventory movements.
     */
    protected function postItemLedger(ProductionJournalLine $line): void
    {
        // BC Logic: Consumption uses 'quantity', Output uses 'output_quantity'
        $qty = $line->entry_type === 'output'
            ? (float) $line->output_quantity
            : -(float) $line->quantity;

        if ($qty === 0.0) return;

        $ledgerEntry = ItemLedgerEntry::create([
            'item_id' => $line->item_id,
            'entry_type' => $line->entry_type,
            'entry_number' => $line->batch->name ?? 'PROD-JNL',
            'posting_date' => $line->posting_date ?? now(),
            'location_id' => $line->location_id,
            'bin_id' => $line->bin_id,
            'quantity' => $qty,
            'unit_of_measure_code' => $line->unit_of_measure_code,
            'source_id' => $line->production_order_no,
            'lot_number' => $line->lot_no,
            'serial_number' => $line->serial_no,
            'cost_amount_actual' => $line->total_cost,
        ]);

        // Link the ledger entry back to the journal line for audit
        $line->item_ledger_entry_id = $ledgerEntry->id;

        // Update Item master inventory (Immediate physical impact)
        $line->item->increment('inventory', $qty);
    }

    /**
     * Handles labor and machine center costs/time.
     */
    protected function postCapacityLedger(ProductionJournalLine $line): void
    {
        $capacityEntry = CapacityLedgerEntry::create([
            'production_order_id' => $line->production_order_id,
            'routing_line_id' => $line->routing_line_id,
            'work_center_id' => $line->work_center_id,
            'machine_center_id' => $line->machine_center_id,
            'posting_date' => $line->posting_date ?? now(),
            'setup_time' => $line->setup_time,
            'run_time' => $line->run_time,
            'stop_time' => $line->stop_time,
            'output_quantity' => $line->output_quantity,
            'scrap_quantity' => $line->scrap_quantity,
            'direct_cost' => $line->direct_cost,
            'overhead_cost' => $line->overhead_cost,
        ]);

        // Link capacity record back for audit
        $line->capacity_ledger_entry_id = $capacityEntry->id;
    }

    /**
     * Utilized for "Automatic Flushing" (BC Feature)
     * Automatically posts lines that are set to Forward/Backward flushing.
     */
    public function flushBatch(\App\Models\ProductionJournalBatch $batch): int
    {
        $postedCount = 0;
        $linesToFlush = $batch->lines()
            ->where('flushed', false)
            ->whereIn('flushing_method', [FlushingMethod::FORWARD, FlushingMethod::BACKWARD])
            ->get();

        foreach ($linesToFlush as $line) {
            if ($this->post($line)) {
                $postedCount++;
            }
        }

        return $postedCount;
    }
}
