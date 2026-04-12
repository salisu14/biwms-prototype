<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseActivityLine extends Model
{
    use HasFactory;

    protected $table = 'warehouse_activity_lines';

    protected $fillable = [
        'warehouse_activity_id',
        'line_no',
        'item_id',
        'quantity_to_handle',
        'quantity_handled',
        'quantity_base',
        'unit_of_measure_code',
        'source_zone_id',
        'source_bin_id',
        'source_lot_no',
        'source_serial_no',
        'destination_zone_id',
        'destination_bin_id',
        'destination_lot_no',
        'destination_serial_no',
        'breakbulk',
        'breakbulk_quantity',
        'lot_no',
        'serial_no',
        'expiration_date',
        'warranty_date',
        'line_status',
        'handled_by',
        'handled_at',
        'remarks',
    ];

    protected $casts = [
        'quantity_to_handle' => 'decimal:4',
        'quantity_handled' => 'decimal:4',
        'quantity_base' => 'decimal:4',
        'breakbulk' => 'boolean',
        'breakbulk_quantity' => 'decimal:4',
        'expiration_date' => 'date',
        'warranty_date' => 'date',
        'handled_at' => 'datetime',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(WarehouseActivity::class, 'warehouse_activity_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function sourceZone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'source_zone_id');
    }

    public function sourceBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'source_bin_id');
    }

    public function destinationZone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'destination_zone_id');
    }

    public function destinationBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'destination_bin_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function isComplete(): bool
    {
        return $this->quantity_handled >= $this->quantity_to_handle;
    }

    public function remainingQuantity(): float
    {
        return max(0, (float) $this->quantity_to_handle - (float) $this->quantity_handled);
    }

    public function complete(float $quantity, ?string $remarks = null): void
    {
        $this->quantity_handled += $quantity;

        if ($this->isComplete()) {
            $this->line_status = 'completed';
            $this->handled_at = now();
        } else {
            $this->line_status = 'in_progress';
        }

        if ($remarks) {
            $this->remarks = $this->remarks ? $this->remarks . "\n" . $remarks : $remarks;
        }

        $this->save();
    }
}
