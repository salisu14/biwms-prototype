<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enums\WarehouseActivityType;
use App\Models\Bin;
use App\Models\BinContent;
use App\Models\Item;
use App\Models\Location;
use App\Models\WarehouseActivity;
use App\Models\WarehouseActivityLine;
use App\Models\WarehouseEntry;
use App\Models\Zone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WarehousePostingService
{
    public function __construct(
        private readonly ItemLedgerService $itemLedgerService,
        private readonly BinAvailabilityService $binAvailabilityService
    ) {}

    /**
     * Post warehouse entry from activity line completion
     */
    public function postWarehouseEntry(
        WarehouseActivityLine $line,
        float $quantity,
        bool $isReversal = false
    ): WarehouseEntry {
        return DB::transaction(function () use ($line, $quantity, $isReversal) {
            $activity = $line->activity;
            $isPositive = match ($activity->activity_type) {
                WarehouseActivityType::PUT_AWAY,
                WarehouseActivityType::RECEIPT,
                WarehouseActivityType::INTERNAL_PUT_AWAY => ! $isReversal,
                WarehouseActivityType::PICK,
                WarehouseActivityType::SHIPMENT,
                WarehouseActivityType::INTERNAL_PICK => $isReversal,
                WarehouseActivityType::MOVEMENT => null, // Handled separately
                default => throw new \InvalidArgumentException('Invalid activity type')
            };

            if ($activity->activity_type === WarehouseActivityType::MOVEMENT) {
                return $this->postMovement($line, $quantity);
            }

            $entry = $this->createEntry(
                item: $line->item,
                location: $activity->location,
                zone: $isPositive ? $line->destinationZone : $line->sourceZone,
                bin: $isPositive ? $line->destinationBin : $line->sourceBin,
                lotNo: $line->lot_no,
                serialNo: $line->serial_no,
                expirationDate: $line->expiration_date,
                entryType: $isPositive ? 'positive' : 'negative',
                quantity: $quantity,
                unitOfMeasure: $line->unit_of_measure_code,
                documentType: $activity->source_document,
                documentNo: $activity->source_no,
                lineNo: $activity->source_line_no,
                activityLineId: $line->id,
                description: "Posted from {$activity->activity_type->label()} {$activity->no}"
            );

            // Update bin content
            $this->updateBinContent($entry);

            // Create item ledger entry if this is a physical inventory transaction
            if ($this->requiresItemLedgerEntry($activity)) {
                $this->itemLedgerService->postFromWarehouseEntry($entry);
            }

            return $entry;
        });
    }

    /**
     * Handle movement (transfer between bins)
     */
    private function postMovement(WarehouseActivityLine $line, float $quantity): WarehouseEntry
    {
        // Negative entry from source
        $negativeEntry = $this->createEntry(
            item: $line->item,
            location: $line->activity->location,
            zone: $line->sourceZone,
            bin: $line->sourceBin,
            lotNo: $line->source_lot_no,
            serialNo: $line->source_serial_no,
            entryType: 'negative',
            quantity: $quantity,
            unitOfMeasure: $line->unit_of_measure_code,
            documentType: $line->activity->source_document,
            documentNo: $line->activity->source_no,
            activityLineId: $line->id,
            description: "Movement from {$line->sourceBin?->bin_code}"
        );

        // Positive entry to destination
        $positiveEntry = $this->createEntry(
            item: $line->item,
            location: $line->activity->location,
            zone: $line->destinationZone,
            bin: $line->destinationBin,
            lotNo: $line->destination_lot_no ?? $line->source_lot_no,
            serialNo: $line->destination_serial_no ?? $line->source_serial_no,
            entryType: 'positive',
            quantity: $quantity,
            unitOfMeasure: $line->unit_of_measure_code,
            documentType: $line->activity->source_document,
            documentNo: $line->activity->source_no,
            activityLineId: $line->id,
            description: "Movement to {$line->destinationBin?->bin_code}"
        );

        $this->updateBinContent($negativeEntry);
        $this->updateBinContent($positiveEntry);

        return $positiveEntry; // Return the "to" entry as primary reference
    }

    private function createEntry(
        Item $item,
        Location $location,
        ?Zone $zone,
        ?Bin $bin,
        ?string $lotNo,
        ?string $serialNo,
        ?\DateTime $expirationDate,
        string $entryType,
        float $quantity,
        string $unitOfMeasure,
        ?string $documentType,
        ?string $documentNo,
        ?int $lineNo,
        int $activityLineId,
        string $description
    ): WarehouseEntry {
        return WarehouseEntry::create([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'zone_id' => $zone?->id,
            'bin_id' => $bin?->id,
            'lot_no' => $lotNo,
            'serial_no' => $serialNo,
            'expiration_date' => $expirationDate,
            'entry_type' => $entryType,
            'quantity' => abs($quantity),
            'quantity_base' => $this->calculateBaseQuantity($item, $unitOfMeasure, abs($quantity)),
            'unit_of_measure_code' => $unitOfMeasure,
            'document_type' => $documentType,
            'document_no' => $documentNo,
            'document_line_no' => $lineNo,
            'warehouse_activity_line_id' => $activityLineId,
            'entry_timestamp' => now(),
            'created_by' => Auth::id(),
            'description' => $description,
        ]);
    }

    private function updateBinContent(WarehouseEntry $entry): void
    {
        if (! $entry->bin_id) {
            return;
        }

        $content = BinContent::firstOrNew([
            'bin_id' => $entry->bin_id,
            'item_id' => $entry->item_id,
            'lot_no' => $entry->lot_no,
            'serial_no' => $entry->serial_no,
        ], [
            'zone_id' => $entry->zone_id,
            'unit_of_measure_code' => $entry->unit_of_measure_code,
        ]);

        $delta = $entry->getSignedQuantity();
        $content->quantity += $delta;

        if ($content->quantity <= 0) {
            $content->delete();
        } else {
            $content->save();
        }
    }

    private function calculateBaseQuantity(Item $item, string $uom, float $quantity): float
    {
        // Convert to base unit of measure
        $uomRecord = $item->unitOfMeasures()->where('code', $uom)->first();
        $qtyPerUom = $uomRecord?->qty_per_base_unit ?? 1;

        return $quantity * $qtyPerUom;
    }

    private function requiresItemLedgerEntry(WarehouseActivity $activity): bool
    {
        // Only certain warehouse activities create item ledger entries
        return in_array($activity->activity_type, [
            WarehouseActivityType::RECEIPT,
            WarehouseActivityType::SHIPMENT,
            WarehouseActivityType::INVENTORY,
        ]);
    }
}
