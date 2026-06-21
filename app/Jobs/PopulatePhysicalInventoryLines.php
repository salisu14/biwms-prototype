<?php

// app/Jobs/PopulatePhysicalInventoryLines.php

namespace App\Jobs;

use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\PhysicalInventoryJournal;
use App\Models\PhysicalInventoryLine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class PopulatePhysicalInventoryLines implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $journalId,
        public array $filters
    ) {}

    public function handle(): void
    {
        $journal = PhysicalInventoryJournal::findOrFail($this->journalId);
        $locationCode = $this->filters['location_code'] ?? $journal->location_code;
        $locationId = Location::query()
            ->where('code', $locationCode)
            ->value('id');

        DB::transaction(function () use ($journal, $locationCode, $locationId) {
            $query = Item::query()
                ->whereHas('ledgerEntries', function ($q) use ($locationId) {
                    $q->where('location_id', $locationId)
                        ->where('open', true);
                });

            // Apply filters
            if (($this->filters['items_filter'] ?? 'all') === 'with_stock') {
                $query->where('inventory', '>', 0);
            }

            $items = $query->get();
            $lineNo = 10000;

            foreach ($items as $item) {
                // Calculate current stock for this location
                $qtyOnHand = ItemLedgerEntry::where('item_id', $item->id)
                    ->where('location_id', $locationId)
                    ->where('open', true)
                    ->sum('remaining_quantity');

                if ($qtyOnHand <= 0 && ($this->filters['items_filter'] ?? 'all') === 'with_stock') {
                    continue;
                }

                // Get tracking info if applicable
                $trackingEntries = ItemLedgerEntry::where('item_id', $item->id)
                    ->where('location_id', $locationId)
                    ->where('open', true)
                    ->where(function ($query): void {
                        $query->whereNotNull('lot_number')
                            ->orWhereNotNull('serial_number');
                    })
                    ->get();

                if ($trackingEntries->isNotEmpty()) {
                    // Create separate lines for each lot/serial
                    foreach ($trackingEntries as $entry) {
                        PhysicalInventoryLine::create([
                            'journal_id' => $journal->id,
                            'line_no' => $lineNo,
                            'item_id' => $item->id,
                            'variant_code' => $entry->variant_code,
                            'location_code' => $locationCode,
                            'bin_code' => $entry->bin_code,
                            'quantity_base' => $entry->remaining_quantity,
                            'qty_physical_inventory' => 0,
                            'qty_calculated' => 0,
                            'unit_of_measure_code' => $item->base_unit_of_measure,
                            'qty_per_unit_of_measure' => 1,
                            'unit_amount' => $item->unit_cost,
                            'item_description' => $item->description,
                            'inventory_posting_group' => $item->inventory_posting_group,
                            'gen_prod_posting_group' => $item->gen_prod_posting_group,
                            'serial_no' => $entry->serial_number,
                            'lot_no' => $entry->lot_number,
                            'expiration_date' => $entry->expiration_date,
                            'use_item_tracking' => ! empty($item->item_tracking_code),
                        ]);
                        $lineNo += 10000;
                    }
                } else {
                    // Single line without tracking
                    PhysicalInventoryLine::create([
                        'journal_id' => $journal->id,
                        'line_no' => $lineNo,
                        'item_id' => $item->id,
                        'location_code' => $locationCode,
                        'quantity_base' => $qtyOnHand,
                        'qty_physical_inventory' => 0,
                        'qty_calculated' => 0,
                        'unit_of_measure_code' => $item->base_unit_of_measure,
                        'qty_per_unit_of_measure' => 1,
                        'unit_amount' => $item->unit_cost,
                        'item_description' => $item->description,
                        'inventory_posting_group' => $item->inventory_posting_group,
                        'gen_prod_posting_group' => $item->gen_prod_posting_group,
                        'use_item_tracking' => ! empty($item->item_tracking_code),
                    ]);
                    $lineNo += 10000;
                }
            }

            $journal->update(['status' => 'Counting']);
        });
    }
}
