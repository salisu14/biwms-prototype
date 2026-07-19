<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningInventoryLine extends Model
{
    protected $fillable = [
        'opening_inventory_id',
        'item_id',
        'location_id',
        'unit_of_measure_id',
        'quantity',
        'quantity_base',
        'unit_cost',
        'amount',
        'line_number',
        'lot_number',
        'serial_number',
        'item_ledger_entry_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:8',
        'quantity_base' => 'decimal:8',
        'unit_cost' => 'decimal:8',
        'amount' => 'decimal:4',
    ];

    public function openingInventory(): BelongsTo
    {
        return $this->belongsTo(OpeningInventory::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    public function itemLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(ItemLedgerEntry::class);
    }
}
