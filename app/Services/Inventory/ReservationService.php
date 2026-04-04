<?php

namespace App\Services\Inventory;

use App\Models\InventoryReservation;
use App\Models\ItemLedgerEntry;
use App\Models\SalesOrderLine;

class ReservationService
{
    public function reserve(SalesOrderLine $line, float $quantity): bool
    {
        if ($quantity > $line->remaining_quantity) {
            return false;
        }

        // TODO: create reservation entry table logic

        $line->increment('reserved_quantity', $quantity);

        return true;
    }

    public function cancel(SalesOrderLine $line, float $quantity): void
    {
        $line->reserved_quantity = max(0, $line->reserved_quantity - $quantity);
        $line->save();
    }

    public function reserveFromOrderLine(SalesOrderLine $line): void
    {
        $availableStocks = $this->getAvailableStock(
            $line->item_id,
            $line->location_id
        );

        $remaining = $line->quantity;

        foreach ($availableStocks as $stock) {

            if ($remaining <= 0) {
                break;
            }

            $allocQty = min($remaining, $stock->available_quantity);

            $this->createReservation(
                itemId: $line->item_id,
                locationId: $stock->location_id,
                binCode: $stock->bin_code,
                quantity: $allocQty,
                line: $line
            );

            $remaining -= $allocQty;
        }

        if ($remaining > 0) {
            throw new \Exception("Insufficient stock for item {$line->item_code}");
        }
    }

    protected function getAvailableStock($itemId, $locationId)
    {
        return ItemLedgerEntry::where('item_id', $itemId)
            ->where('location_id', $locationId)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('created_at') // FIFO
            ->get();
    }

    protected function createReservation(
        int $itemId,
        int $locationId,
        ?string $binCode,
        float $quantity,
        SalesOrderLine $line
    ) {
        InventoryReservation::create([
            'item_id' => $itemId,
            'location_id' => $locationId,
            'bin_code' => $binCode,
            'quantity' => $quantity,
            'source_type' => 'SALES_ORDER',
            'source_id' => $line->sales_order_id,
            'source_line_id' => $line->id,
            'status' => 'RESERVED',
        ]);
    }
}
