<?php

// app/Jobs/SuggestPhysicalInventoryLines.php

namespace App\Jobs;

use App\Models\BinContent;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\PhysicalInventoryJournal;
use App\Models\PhysicalInventoryLine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SuggestPhysicalInventoryLines implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $journalId,
        public array $data
    ) {}

    public function handle(): void
    {
        $journal = PhysicalInventoryJournal::findOrFail($this->journalId);
        $filterType = $this->data['filter_type'] ?? 'all';
        $includeEmptyBins = $this->data['include_empty_bins'] ?? false;

        DB::transaction(function () use ($journal, $filterType, $includeEmptyBins) {
            $lineNo = PhysicalInventoryLine::where('journal_id', $journal->id)->max('line_no') ?? 0;
            $lineNo = max($lineNo, 10000);

            if ($filterType === 'bin_mandatory') {
                // Suggest based on bin contents
                $binContents = BinContent::with('item')
                    ->when($journal->location_code, fn ($q) => $q->where('location_code', $journal->location_code))
                    ->when($journal->bin_code, fn ($q) => $q->where('bin_code', $journal->bin_code))
                    ->get();

                foreach ($binContents as $content) {
                    $lineNo += 10000;
                    PhysicalInventoryLine::create([
                        'journal_id' => $journal->id,
                        'line_no' => $lineNo,
                        'item_id' => $content->item_id,
                        'location_code' => $content->location_code,
                        'bin_code' => $content->bin_code,
                        'quantity_base' => $content->quantity,
                        'qty_physical_inventory' => 0,
                        'unit_of_measure_code' => $content->unit_of_measure_code,
                        'qty_per_unit_of_measure' => $content->qty_per_unit_of_measure,
                        'unit_amount' => $content->item?->unit_cost,
                        'item_description' => $content->item?->description,
                        'inventory_posting_group' => $content->item?->inventory_posting_group,
                        'gen_prod_posting_group' => $content->item?->gen_prod_posting_group,
                    ]);
                }

                if ($includeEmptyBins) {
                    // Add empty bins logic here if needed
                }
            } else {
                // Standard item-based suggestion
                $items = Item::query()
                    ->when($filterType === 'with_stock', fn ($q) => $q->where('inventory', '>', 0))
                    ->when($journal->location_code, function ($q) use ($journal) {
                        $q->whereHas('itemLedgerEntries', fn ($sq) => $sq->where('location_code', $journal->location_code)
                        );
                    })
                    ->get();

                foreach ($items as $item) {
                    $qtyOnHand = $journal->location_code
                        ? ItemLedgerEntry::where('item_id', $item->id)
                            ->where('location_code', $journal->location_code)
                            ->where('open', true)
                            ->sum('remaining_quantity')
                        : $item->inventory;

                    $lineNo += 10000;
                    PhysicalInventoryLine::create([
                        'journal_id' => $journal->id,
                        'line_no' => $lineNo,
                        'item_id' => $item->id,
                        'location_code' => $journal->location_code,
                        'quantity_base' => $qtyOnHand,
                        'qty_physical_inventory' => 0,
                        'unit_of_measure_code' => $item->base_unit_of_measure,
                        'unit_amount' => $item->unit_cost,
                        'item_description' => $item->description,
                        'inventory_posting_group' => $item->inventory_posting_group,
                        'gen_prod_posting_group' => $item->gen_prod_posting_group,
                    ]);
                }
            }
        });
    }
}
