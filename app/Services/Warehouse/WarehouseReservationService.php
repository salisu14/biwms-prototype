<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Models\BinContent;

class WarehouseReservationService
{
    /**
     * Reserve inventory for specific purpose
     */
    public function reserve(
        BinContent $binContent,
        float $quantity,
        string $reservationType, // pick, production, sales, transfer
        string $referenceNo,
        int $referenceLineNo,
        ?\DateTime $expiration = null
    ): void {
        if ($binContent->availableQuantity() < $quantity) {
            throw new \RuntimeException('Insufficient available quantity to reserve');
        }

        $binContent->reserve($quantity);

        // Create reservation record if tracking detailed reservations
        // Reservation::create([...]);
    }

    /**
     * Release reservation
     */
    public function release(
        BinContent $binContent,
        float $quantity
    ): void {
        $binContent->releaseReservation($quantity);
    }

    /**
     * Consume reserved inventory (when pick is posted)
     */
    public function consume(
        BinContent $binContent,
        float $quantity
    ): void {
        // Reduce both reserved and actual quantity
        $binContent->picked_quantity -= $quantity;
        $binContent->quantity -= $quantity;

        if ($binContent->quantity <= 0) {
            $binContent->delete();
        } else {
            $binContent->save();
        }
    }
}
