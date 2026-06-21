<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'location_id',
        'zone_id',
        'bin_id',
        'lot_no',
        'serial_no',
        'expiration_date',
        'entry_type',
        'quantity',
        'quantity_base',
        'unit_of_measure_code',
        'unit_cost',
        'total_cost',
        'document_type',
        'document_no',
        'document_line_no',
        'warehouse_activity_line_id',
        'item_ledger_entry_id',
        'entry_timestamp',
        'created_by',
        'description',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'quantity_base' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'expiration_date' => 'date',
        'entry_timestamp' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'bin_id');
    }

    public function activityLine(): BelongsTo
    {
        return $this->belongsTo(WarehouseActivityLine::class, 'warehouse_activity_line_id');
    }

    public function isPositive(): bool
    {
        return $this->entry_type === 'positive';
    }

    public function isNegative(): bool
    {
        return $this->entry_type === 'negative';
    }

    public function getSignedQuantity(): float
    {
        return $this->isPositive() ? (float) $this->quantity : -(float) $this->quantity;
    }
}
