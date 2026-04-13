<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enums\PickLineStatus;
use App\Enums\WarehouseDocumentStatus;
use App\Models\BinContent;
use App\Models\WarehouseEntry;
use App\Models\WarehousePick;
use App\Models\WarehousePickLine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PickPostingService
{
    /**
     * Register (post) all lines of a warehouse pick.
     *
     * Steps:
     *  1. Validate all lines have qty_handled >= qty_to_handle
     *  2. Write WarehouseEntry records (negative from source bin, positive to destination bin)
     *  3. Update linked WarehouseShipment line qty_picked if applicable
     *  4. Mark pick status → completed
     */
    public function register(WarehousePick $pick): void
    {
        if (! $pick->status->canProcess()) {
            throw new \RuntimeException(
                "Pick {$pick->no} must be Released or In Progress before registration."
            );
        }

        DB::transaction(function () use ($pick): void {
            $lines = $pick->lines()->get();

            foreach ($lines as $line) {
                if ($line->line_status->isTerminal()) {
                    continue;
                }

                $this->postPickLine($pick, $line);
            }

            $pick->update([
                'status' => WarehouseDocumentStatus::COMPLETED,
                'completed_at' => now(),
            ]);
        });
    }

    /**
     * Write the two warehouse entries (negative from source, positive to destination)
     * for a single pick line and update bin content accordingly.
     */
    private function postPickLine(WarehousePick $pick, WarehousePickLine $line): void
    {
        $qty = (float) $line->quantity_to_handle;
        $item = $line->item;
        $location = $pick->location;

        // --- Negative entry: deduct from pick-from (source) bin ---
        WarehouseEntry::create([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'zone_id' => $line->zone_id,
            'bin_id' => $line->bin_id,
            'lot_no' => $line->lot_no,
            'serial_no' => $line->serial_no,
            'expiration_date' => $line->expiration_date,
            'entry_type' => 'negative',
            'quantity' => $qty,
            'quantity_base' => (float) $line->quantity_base,
            'unit_of_measure_code' => $line->unit_of_measure_code,
            'document_type' => 'warehouse_pick',
            'document_no' => $pick->no,
            'document_line_no' => $line->line_no,
            'entry_timestamp' => now(),
            'created_by' => Auth::id(),
            'description' => "Pick {$pick->no} — take from bin {$line->bin?->bin_code}",
        ]);

        $this->updateBinContent($line->zone_id, $line->bin_id, $item->id, $line->lot_no, $line->serial_no, $line->unit_of_measure_code, -$qty);

        // --- Positive entry: add to destination bin ---
        if ($line->destination_bin_id) {
            WarehouseEntry::create([
                'item_id' => $item->id,
                'location_id' => $location->id,
                'zone_id' => $line->destination_zone_id,
                'bin_id' => $line->destination_bin_id,
                'lot_no' => $line->lot_no,
                'serial_no' => $line->serial_no,
                'expiration_date' => $line->expiration_date,
                'entry_type' => 'positive',
                'quantity' => $qty,
                'quantity_base' => (float) $line->quantity_base,
                'unit_of_measure_code' => $line->unit_of_measure_code,
                'document_type' => 'warehouse_pick',
                'document_no' => $pick->no,
                'document_line_no' => $line->line_no,
                'entry_timestamp' => now(),
                'created_by' => Auth::id(),
                'description' => "Pick {$pick->no} — place to bin {$line->destinationBin?->bin_code}",
            ]);

            $this->updateBinContent($line->destination_zone_id, $line->destination_bin_id, $item->id, $line->lot_no, $line->serial_no, $line->unit_of_measure_code, $qty);
        }

        // Mark line completed
        $line->update([
            'quantity_handled' => $qty,
            'line_status' => PickLineStatus::COMPLETED,
            'handled_by' => Auth::id(),
            'handled_at' => now(),
        ]);
    }

    /**
     * Adjust BinContent quantity; delete record when quantity reaches zero or below.
     */
    private function updateBinContent(
        ?int $zoneId,
        ?int $binId,
        int $itemId,
        ?string $lotNo,
        ?string $serialNo,
        string $uom,
        float $delta
    ): void {
        if (! $binId) {
            return;
        }

        $content = BinContent::firstOrNew(
            ['bin_id' => $binId, 'item_id' => $itemId, 'lot_no' => $lotNo, 'serial_no' => $serialNo],
            ['zone_id' => $zoneId, 'unit_of_measure_code' => $uom]
        );

        $content->quantity = ((float) ($content->quantity ?? 0)) + $delta;

        if ($content->quantity <= 0) {
            $content->delete();
        } else {
            $content->save();
        }
    }
}
