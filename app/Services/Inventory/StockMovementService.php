<?php

namespace App\Services\Inventory;

use App\Enums\ItemLedgerEntryType;
use App\Enums\ShipmentStatus;
use App\Enums\WarehouseReceiptStatus;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\WarehouseReceipt;
use App\Models\WarehouseReceiptLine;
use App\Models\WarehouseShipment;
use App\Models\WarehouseShipmentLine;
use Illuminate\Support\Facades\DB;

class StockMovementService
{
    /**
     * @return array{source: ItemLedgerEntry, destination: ItemLedgerEntry}
     */
    public function transfer(
        Item $item,
        Location $sourceLocation,
        Location $destinationLocation,
        float $quantityBase,
        string $documentNumber,
        mixed $postingDate = null,
    ): array {
        if ($sourceLocation->is($destinationLocation)) {
            throw new \RuntimeException('Transfer source and destination locations must be different.');
        }

        if ($quantityBase <= 0) {
            throw new \RuntimeException('Transfer quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($item, $sourceLocation, $destinationLocation, $quantityBase, $documentNumber, $postingDate): array {
            $item = Item::query()->lockForUpdate()->findOrFail($item->id);

            if ($this->locationAvailableQuantity($item, $sourceLocation) < $quantityBase) {
                throw new \RuntimeException("Insufficient stock at source location {$sourceLocation->code} for item {$item->item_code}.");
            }

            if ($this->warehouseLedgerEntriesExist('WAREHOUSE_TRANSFER', $documentNumber)) {
                throw new \RuntimeException("Warehouse transfer {$documentNumber} has already been posted.");
            }

            $sourceEntry = $this->createItemLedgerEntry(
                item: $item,
                location: $sourceLocation,
                entryType: ItemLedgerEntryType::TRANSFER,
                quantityBase: -$quantityBase,
                documentType: 'WAREHOUSE_TRANSFER',
                documentNumber: $documentNumber,
                documentLineNumber: 10000,
                postingDate: $postingDate,
            );

            $destinationEntry = $this->createItemLedgerEntry(
                item: $item,
                location: $destinationLocation,
                entryType: ItemLedgerEntryType::TRANSFER,
                quantityBase: $quantityBase,
                documentType: 'WAREHOUSE_TRANSFER',
                documentNumber: $documentNumber,
                documentLineNumber: 20000,
                postingDate: $postingDate,
            );

            return [
                'source' => $sourceEntry,
                'destination' => $destinationEntry,
            ];
        });
    }

    public function postWarehouseReceipt(WarehouseReceipt $receipt): WarehouseReceipt
    {
        return DB::transaction(function () use ($receipt): WarehouseReceipt {
            $receipt = WarehouseReceipt::query()
                ->with('lines.item', 'location')
                ->lockForUpdate()
                ->findOrFail($receipt->id);

            if ($receipt->status === WarehouseReceiptStatus::RECEIVED || $receipt->posted_date) {
                throw new \RuntimeException("Warehouse receipt {$receipt->document_number} has already been posted.");
            }

            if (! in_array($receipt->status, [WarehouseReceiptStatus::RELEASED, WarehouseReceiptStatus::PARTIALLY_RECEIVED], true)) {
                throw new \RuntimeException('Only released warehouse receipts can be posted.');
            }

            if ($this->warehouseLedgerEntriesExist('WAREHOUSE_RECEIPT', $receipt->document_number)) {
                throw new \RuntimeException("Warehouse receipt {$receipt->document_number} has already been posted.");
            }

            foreach ($receipt->lines as $line) {
                $quantityBase = $this->receiptLineQuantityBase($line);

                if ($quantityBase <= 0) {
                    continue;
                }

                $entry = $this->createItemLedgerEntry(
                    item: $line->item,
                    location: $receipt->location,
                    entryType: ItemLedgerEntryType::POSITIVE_ADJUSTMENT,
                    quantityBase: $quantityBase,
                    documentType: 'WAREHOUSE_RECEIPT',
                    documentNumber: $receipt->document_number,
                    documentLineNumber: (int) $line->line_number,
                    postingDate: $receipt->receipt_date,
                    source: $receipt,
                );

                $line->forceFill([
                    'quantity_received' => (float) $line->quantity,
                ])->save();

                $line->item->increment('inventory', $quantityBase);
            }

            $receipt->forceFill([
                'status' => WarehouseReceiptStatus::RECEIVED,
                'posted_date' => now(),
            ])->save();

            return $receipt->fresh('lines');
        });
    }

    public function postWarehouseShipment(WarehouseShipment $shipment): WarehouseShipment
    {
        return DB::transaction(function () use ($shipment): WarehouseShipment {
            $shipment = WarehouseShipment::query()
                ->with('lines.item', 'location')
                ->lockForUpdate()
                ->findOrFail($shipment->id);

            if ($shipment->status === ShipmentStatus::SHIPPED->value || $shipment->posted_date) {
                throw new \RuntimeException("Warehouse shipment {$shipment->document_number} has already been posted.");
            }

            if (! in_array($shipment->status, [ShipmentStatus::RELEASED->value, ShipmentStatus::PARTIALLY_SHIPPED->value], true)) {
                throw new \RuntimeException('Only released warehouse shipments can be posted.');
            }

            if ($this->warehouseLedgerEntriesExist('WAREHOUSE_SHIPMENT', $shipment->document_number)) {
                throw new \RuntimeException("Warehouse shipment {$shipment->document_number} has already been posted.");
            }

            foreach ($shipment->lines as $line) {
                $quantityBase = $this->shipmentLineQuantityBase($line);

                if ($quantityBase <= 0) {
                    continue;
                }

                if ($this->locationAvailableQuantity($line->item, $shipment->location) < $quantityBase) {
                    throw new \RuntimeException("Insufficient stock at location {$shipment->location->code} for item {$line->item->item_code}.");
                }

                $this->createItemLedgerEntry(
                    item: $line->item,
                    location: $shipment->location,
                    entryType: ItemLedgerEntryType::NEGATIVE_ADJUSTMENT,
                    quantityBase: -$quantityBase,
                    documentType: 'WAREHOUSE_SHIPMENT',
                    documentNumber: $shipment->document_number,
                    documentLineNumber: (int) $line->line_number,
                    postingDate: $shipment->shipment_date,
                    source: $shipment,
                );

                $line->forceFill([
                    'quantity_shipped' => (float) $line->quantity,
                ])->save();

                $line->item->decrement('inventory', $quantityBase);
            }

            $shipment->forceFill([
                'status' => ShipmentStatus::SHIPPED->value,
                'posted_date' => now(),
            ])->save();

            return $shipment->fresh('lines');
        });
    }

    public function locationAvailableQuantity(Item $item, Location $location): float
    {
        return (float) ItemLedgerEntry::query()
            ->where('item_id', $item->id)
            ->where('location_id', $location->id)
            ->sum('quantity');
    }

    private function createItemLedgerEntry(
        Item $item,
        Location $location,
        ItemLedgerEntryType $entryType,
        float $quantityBase,
        string $documentType,
        string $documentNumber,
        int $documentLineNumber,
        mixed $postingDate,
        ?object $source = null,
    ): ItemLedgerEntry {
        $costAmount = abs($quantityBase) * (float) ($item->unit_cost ?? 0);

        return ItemLedgerEntry::query()->create([
            'entry_type' => $entryType,
            'document_type' => $documentType,
            'document_number' => $documentNumber,
            'document_line_number' => $documentLineNumber,
            'item_id' => $item->id,
            'location_id' => $location->id,
            'quantity' => $quantityBase,
            'remaining_quantity' => max(0, $quantityBase),
            'open' => $quantityBase > 0,
            'posting_date' => $postingDate ?? now(),
            'entry_date' => now(),
            'source_id' => $source?->id,
            'source_type' => $source ? $source::class : self::class,
            'cost_amount_actual' => $costAmount,
            'cost_amount_expected' => 0,
            'general_product_posting_group_id' => $item->general_product_posting_group_id,
            'inventory_posting_group_id' => $item->inventory_posting_group_id,
        ]);
    }

    private function receiptLineQuantityBase(WarehouseReceiptLine $line): float
    {
        return (float) $line->quantity * $this->quantityPerUnitOfMeasure((float) $line->qty_per_unit_of_measure);
    }

    private function shipmentLineQuantityBase(WarehouseShipmentLine $line): float
    {
        return (float) $line->quantity * $this->quantityPerUnitOfMeasure((float) $line->qty_per_unit_of_measure);
    }

    private function quantityPerUnitOfMeasure(float $quantityPerUnitOfMeasure): float
    {
        return $quantityPerUnitOfMeasure > 0 ? $quantityPerUnitOfMeasure : 1.0;
    }

    private function warehouseLedgerEntriesExist(string $documentType, string $documentNumber): bool
    {
        return ItemLedgerEntry::query()
            ->where('document_type', $documentType)
            ->where('document_number', $documentNumber)
            ->exists();
    }
}
