<?php

declare(strict_types=1);

namespace App\Services\Posting;

use App\Models\Bin;
use App\Models\BinContent;
use App\Models\WarehouseEntry;
use App\Models\WarehouseJournalLine;
use Illuminate\Support\Facades\Auth;

class WarehouseJournalPostingRoutine extends AbstractJournalPostingRoutine
{
    /**
     * @param  WarehouseJournalLine  $line
     */
    protected function validateLine(object $line): void
    {
        $template = $line->batch->template;

        if ($template->zone_mandatory && ! $line->zone_id && ! $line->source_zone_id) {
            $this->errors[] = "Line {$line->line_no}: Zone is mandatory";
        }

        if ($template->bin_mandatory && ! $line->bin_id && ! $line->source_bin_id) {
            $this->errors[] = "Line {$line->line_no}: Bin is mandatory";
        }

        // Check source bin has inventory
        if ($line->entry_type !== 'put_away') {
            $available = $this->getBinAvailableQuantity($line);
            if ($available < $line->quantity) {
                $this->errors[] = "Line {$line->line_no}: Insufficient quantity in source bin. Available: {$available}";
            }
        }

        // Check destination bin accepts item
        if ($line->destination_bin_id) {
            $bin = Bin::find($line->destination_bin_id);
            if (! $bin->acceptsItem($line->item)) {
                $this->errors[] = "Line {$line->line_no}: Destination bin does not accept this item";
            }
        }
    }

    /**
     * @param  WarehouseJournalLine  $line
     */
    protected function postLine(object $line): void
    {
        $entryType = match ($line->entry_type) {
            'pick' => 'negative',
            'put_away' => 'positive',
            'movement' => 'transfer',
            'positive_adj' => 'positive',
            'negative_adj' => 'negative',
            default => 'adjustment',
        };

        // Source entry (negative for pick/movement)
        if (in_array($line->entry_type, ['pick', 'movement', 'negative_adj'])) {
            WarehouseEntry::create([
                'item_id' => $line->item_id,
                'location_id' => $line->source_location_id ?? $line->location_id,
                'zone_id' => $line->source_zone_id,
                'bin_id' => $line->source_bin_id,
                'lot_no' => $line->source_lot_no ?? $line->lot_no,
                'serial_no' => $line->source_serial_no ?? $line->serial_no,
                'entry_type' => 'negative',
                'quantity' => $line->quantity,
                'quantity_base' => $line->quantity_base,
                'unit_of_measure_code' => $line->unit_of_measure_code,
                'document_type' => 'warehouse_journal',
                'document_no' => $line->document_no,
                'document_line_no' => $line->line_no,
                'entry_timestamp' => now(),
                'created_by' => Auth::id(),
            ]);

            $this->updateSourceBinContent($line);
        }

        // Destination entry (positive for put-away/movement)
        if (in_array($line->entry_type, ['put_away', 'movement', 'positive_adj'])) {
            WarehouseEntry::create([
                'item_id' => $line->item_id,
                'location_id' => $line->destination_location_id ?? $line->location_id,
                'zone_id' => $line->destination_zone_id,
                'bin_id' => $line->destination_bin_id,
                'lot_no' => $line->destination_lot_no ?? $line->lot_no,
                'serial_no' => $line->destination_serial_no ?? $line->serial_no,
                'entry_type' => 'positive',
                'quantity' => $line->quantity,
                'quantity_base' => $line->quantity_base,
                'unit_of_measure_code' => $line->unit_of_measure_code,
                'document_type' => 'warehouse_journal',
                'document_no' => $line->document_no,
                'document_line_no' => $line->line_no,
                'entry_timestamp' => now(),
                'created_by' => Auth::id(),
            ]);

            $this->updateDestinationBinContent($line);
        }

        // Update warehouse activity if linked
        if ($line->warehouse_activity_line_id) {
            $line->warehouseActivityLine->complete($line->quantity);
        }

        $this->updateLineStatus($line, 'posted');
    }

    private function updateSourceBinContent(WarehouseJournalLine $line): void
    {
        $content = BinContent::where([
            'bin_id' => $line->source_bin_id ?? $line->bin_id,
            'item_id' => $line->item_id,
            'lot_no' => $line->source_lot_no ?? $line->lot_no,
        ])->first();

        if ($content) {
            $content->quantity -= $line->quantity;
            $content->save();
        }
    }

    private function updateDestinationBinContent(WarehouseJournalLine $line): void
    {
        $content = BinContent::firstOrNew([
            'bin_id' => $line->destination_bin_id,
            'item_id' => $line->item_id,
            'lot_no' => $line->destination_lot_no ?? $line->lot_no,
        ]);

        $content->quantity += $line->quantity;
        $content->save();
    }

    private function getBinAvailableQuantity(WarehouseJournalLine $line): float
    {
        $content = BinContent::where([
            'bin_id' => $line->source_bin_id ?? $line->bin_id,
            'item_id' => $line->item_id,
            'lot_no' => $line->source_lot_no ?? $line->lot_no,
        ])->first();

        return $content?->availableQuantity() ?? 0;
    }
}
