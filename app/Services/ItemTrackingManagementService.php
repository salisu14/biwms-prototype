<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\ItemTrackingLine;
use App\Models\ReservationEntry;
use App\Models\SalesOrderLine;
use App\Models\SalesShipmentLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * BC equivalent: Codeunit 6500 "Item Tracking Management"
 * Handles Lot No., Serial No., Expiration Date, Warranty Date
 */
class ItemTrackingManagementService
{
    private bool $strictExpirationPosting = true;

    private bool $allowExpiredLot = false;

    /**
     * Copy tracking from Order Line to Shipment Line (BC: CopyTracking)
     */
    public function copyTrackingToShipment(
        SalesOrderLine $orderLine,
        SalesShipmentLine $shipmentLine,
        float $qtyToShip
    ): void {
        $trackingLines = ItemTrackingLine::where('source_type', 'sales_order')
            ->where('source_id', $orderLine->id)
            ->where('quantity', '>', 0)
            ->get();

        $remainingToShip = $qtyToShip;

        foreach ($trackingLines as $tracking) {
            if ($remainingToShip <= 0) {
                break;
            }

            $shipQty = min($tracking->quantity, $remainingToShip);

            ReservationEntry::create([
                'source_type' => SalesShipmentLine::class,
                'source_id' => $shipmentLine->id,
                'item_no' => $shipmentLine->no,
                'variant_code' => $shipmentLine->variant_code,
                'serial_no' => $tracking->serial_no,
                'lot_no' => $tracking->lot_no,
                'expiration_date' => $tracking->expiration_date,
                'warranty_date' => $tracking->warranty_date,
                'quantity' => -$shipQty,
                'quantity_base' => -($shipQty * $shipmentLine->qty_per_unit_of_measure),
                'reservation_status' => 'tracking',
                'source_subtype' => 'sales_shipment',
                'source_ref_no' => $shipmentLine->line_no,
            ]);

            // Update order tracking quantity handled
            $tracking->decrement('quantity', $shipQty);
            $remainingToShip -= $shipQty;
        }

        if ($remainingToShip > 0) {
            throw new \RuntimeException(
                "Insufficient item tracking specified. Missing quantity: {$remainingToShip}"
            );
        }
    }

    /**
     * Get tracked quantity for a line (BC: CalcQtyToHandle)
     */
    public function getTrackedQuantity($line): float
    {
        return ItemTrackingLine::where('source_type', $this->getSourceType($line))
            ->where('source_id', $line->id)
            ->sum('quantity');
    }

    /**
     * Get available tracking for item (BC: LookupTrackingAvailability)
     */
    public function lookupTrackingAvailability(
        string $itemNo,
        ?string $variantCode = null,
        ?string $locationCode = null
    ): Collection {
        return ItemLedgerEntry::where('item_no', $itemNo)
            ->where('open', true)
            ->where('quantity', '>', 0)
            ->when($variantCode, fn ($q) => $q->where('variant_code', $variantCode))
            ->when($locationCode, fn ($q) => $q->where('location_code', $locationCode))
            ->whereNotNull('lot_no')
            ->orWhereNotNull('serial_no')
            ->select([
                'lot_no',
                'serial_no',
                'expiration_date',
                'warranty_date',
                DB::raw('SUM(quantity) as available_quantity'),
                'location_code',
                'variant_code',
            ])
            ->groupBy(['lot_no', 'serial_no', 'location_code', 'variant_code', 'expiration_date', 'warranty_date'])
            ->get();
    }

    /**
     * Reserve specific lot/serial numbers (BC: CreateReservation)
     */
    public function createReservation(
        string $itemNo,
        string $locationCode,
        ?string $lotNo,
        ?string $serialNo,
        float $quantity,
        string $sourceType,
        int $sourceId,
        ?\DateTimeInterface $expirationDate = null
    ): ReservationEntry {

        // Check availability
        $available = $this->getAvailableTrackingQty($itemNo, $locationCode, $lotNo, $serialNo);

        if ($available < $quantity) {
            throw new \RuntimeException(
                "Insufficient quantity available for reservation. Available: {$available}, Requested: {$quantity}"
            );
        }

        // Check expiration
        if ($this->strictExpirationPosting && $expirationDate && $expirationDate < now()) {
            if (! $this->allowExpiredLot) {
                throw new \RuntimeException("Lot {$lotNo} has expired");
            }
        }

        return ReservationEntry::create([
            'item_no' => $itemNo,
            'location_code' => $locationCode,
            'lot_no' => $lotNo,
            'serial_no' => $serialNo,
            'quantity' => $quantity,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'expiration_date' => $expirationDate,
            'reservation_date' => now(),
        ]);
    }

    /**
     * Suggest tracking lines based on FEFO/FIFO (BC: SuggestTracking)
     *
     * @param  string  $method  'FEFO' (First Expired First Out), 'FIFO', 'LIFO'
     */
    public function suggestTracking(
        string $itemNo,
        float $quantityNeeded,
        string $method = 'FEFO'
    ): Collection {

        $query = ItemLedgerEntry::where('item_no', $itemNo)
            ->where('open', true)
            ->where('quantity', '>', 0)
            ->whereNotNull('lot_no');

        switch ($method) {
            case 'FEFO':
                $query->orderBy('expiration_date', 'asc');
                break;
            case 'FIFO':
                $query->orderBy('posting_date', 'asc');
                break;
            case 'LIFO':
                $query->orderBy('posting_date', 'desc');
                break;
        }

        $entries = $query->get();
        $suggestions = collect();
        $remaining = $quantityNeeded;

        foreach ($entries as $entry) {
            if ($remaining <= 0) {
                break;
            }

            $suggestQty = min($entry->quantity, $remaining);
            $suggestions->push([
                'lot_no' => $entry->lot_no,
                'serial_no' => $entry->serial_no,
                'expiration_date' => $entry->expiration_date,
                'available_quantity' => $entry->quantity,
                'suggested_quantity' => $suggestQty,
                'location_code' => $entry->location_code,
            ]);

            $remaining -= $suggestQty;
        }

        return $suggestions;
    }

    /**
     * Validate item tracking setup (BC: CheckItemTracking)
     */
    public function checkItemTracking(string $itemNo, array $trackingData): void
    {
        $item = Item::where('item_no', $itemNo)->first();
        if (! $item) {
            return;
        }

        $trackingCode = $item->item_tracking_code;
        if (empty($trackingCode)) {
            return;
        } // No tracking required

        $setup = $this->getItemTrackingSetup($trackingCode);

        if ($setup['lot_specific'] && empty($trackingData['lot_no'])) {
            throw new \RuntimeException("Lot No. is required for item {$itemNo}");
        }

        if ($setup['serial_no_specific'] && empty($trackingData['serial_no'])) {
            throw new \RuntimeException("Serial No. is required for item {$itemNo}");
        }

        // Check expiration date requirement
        if ($setup['man_expir_date_entry_reqd'] && empty($trackingData['expiration_date'])) {
            throw new \RuntimeException("Expiration Date is required for item {$itemNo}");
        }

        // Validate serial number uniqueness
        if (! empty($trackingData['serial_no'])) {
            $this->validateSerialNoUnique($itemNo, $trackingData['serial_no']);
        }
    }

    /**
     * Collect expiration dates from tracking (BC: CollectExpDate)
     */
    public function collectExpirationDate(
        string $itemNo,
        ?string $lotNo,
        ?string $serialNo
    ): ?\DateTimeInterface {

        return ItemLedgerEntry::where('item_no', $itemNo)
            ->when($lotNo, fn ($q) => $q->where('lot_no', $lotNo))
            ->when($serialNo, fn ($q) => $q->where('serial_no', $serialNo))
            ->whereNotNull('expiration_date')
            ->value('expiration_date');
    }

    /**
     * Update expiration date on all entries (BC: UpdateExpirationDate)
     */
    public function updateExpirationDate(
        string $itemNo,
        string $lotNo,
        \DateTimeInterface $newExpirationDate
    ): void {
        DB::transaction(function () use ($itemNo, $lotNo, $newExpirationDate) {
            ItemLedgerEntry::where('item_no', $itemNo)
                ->where('lot_no', $lotNo)
                ->update(['expiration_date' => $newExpirationDate]);

            ReservationEntry::where('item_no', $itemNo)
                ->where('lot_no', $lotNo)
                ->update(['expiration_date' => $newExpirationDate]);
        });
    }

    /**
     * Register item tracking lines in temporary buffer (BC: RegisterItemTrackingLines)
     */
    public function registerItemTrackingLines(
        string $sourceType,
        int $sourceId,
        array $trackingLines
    ): void {
        foreach ($trackingLines as $line) {
            ItemTrackingLine::updateOrCreate([
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'lot_no' => $line['lot_no'] ?? null,
                'serial_no' => $line['serial_no'] ?? null,
            ], [
                'item_no' => $line['item_no'],
                'quantity' => $line['quantity'],
                'expiration_date' => $line['expiration_date'] ?? null,
                'warranty_date' => $line['warranty_date'] ?? null,
            ]);
        }

        // Clean up zero-quantity lines
        ItemTrackingLine::where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('quantity', '<=', 0)
            ->delete();
    }

    /**
     * Check if tracking is required for item (BC: IsItemTrackingRequired)
     */
    public function isItemTrackingRequired(string $itemNo): bool
    {
        return Item::where('item_no', $itemNo)
            ->whereNotNull('item_tracking_code')
            ->where('item_tracking_code', '!=', '')
            ->exists();
    }

    /**
     * Get tracking specification for posting (BC: GetTrackingSpecification)
     */
    public function getTrackingSpecification($line): Collection
    {
        return ReservationEntry::where('source_type', get_class($line))
            ->where('source_id', $line->id)
            ->where('reservation_status', 'tracking')
            ->get()
            ->map(fn ($entry) => [
                'lot_no' => $entry->lot_no,
                'serial_no' => $entry->serial_no,
                'quantity' => abs($entry->quantity),
                'expiration_date' => $entry->expiration_date,
                'warranty_date' => $entry->warranty_date,
            ]);
    }

    /**
     * Zero-tracking line check (BC: TrackingExists)
     */
    public function trackingExists($line): bool
    {
        return ItemTrackingLine::where('source_type', $this->getSourceType($line))
            ->where('source_id', $line->id)
            ->where('quantity', '>', 0)
            ->exists();
    }

    /**
     * Calculate total tracking quantity (BC: CalcTotalTrackingQty)
     */
    public function calcTotalTrackingQty($line): float
    {
        return ItemTrackingLine::where('source_type', $this->getSourceType($line))
            ->where('source_id', $line->id)
            ->sum('quantity');
    }

    /**
     * Check if tracking quantity matches document quantity (BC: TrackingQtyMatch)
     */
    public function trackingQtyMatch($line, float $docQty): bool
    {
        $trackingQty = $this->calcTotalTrackingQty($line);

        return abs($trackingQty - $docQty) < 0.00001;
    }

    private function getAvailableTrackingQty(
        string $itemNo,
        string $locationCode,
        ?string $lotNo,
        ?string $serialNo
    ): float {
        return ItemLedgerEntry::where('item_no', $itemNo)
            ->where('location_code', $locationCode)
            ->when($lotNo, fn ($q) => $q->where('lot_no', $lotNo))
            ->when($serialNo, fn ($q) => $q->where('serial_no', $serialNo))
            ->where('open', true)
            ->sum('quantity');
    }

    private function validateSerialNoUnique(string $itemNo, string $serialNo): void
    {
        // Check if serial number already used in inventory
        $exists = ItemLedgerEntry::where('item_no', $itemNo)
            ->where('serial_no', $serialNo)
            ->where('open', true)
            ->exists();

        if ($exists) {
            throw new \RuntimeException(
                "Serial No. {$serialNo} already exists in inventory for item {$itemNo}"
            );
        }
    }

    private function getSourceType($line): string
    {
        return match (get_class($line)) {
            SalesOrderLine::class => 'sales_order',
            SalesShipmentLine::class => 'sales_shipment',
            default => strtolower(class_basename($line)),
        };
    }

    private function getItemTrackingSetup(string $code): array
    {
        return Cache::remember("tracking_setup_{$code}", 3600, function () use ($code) {
            return DB::table('item_tracking_codes')
                ->where('code', $code)
                ->first()?->toArray() ?? [];
        });
    }
}
