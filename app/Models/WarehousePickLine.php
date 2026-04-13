<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PickLineStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehousePickLine extends Model
{
    use HasFactory;

    protected $table = 'warehouse_pick_lines';

    protected $fillable = [
        'warehouse_pick_id',
        'line_no',
        'source_line_no',
        'item_id',
        'description',
        'quantity',
        'quantity_to_handle',
        'quantity_handled',
        'quantity_base',
        'unit_of_measure_code',
        'zone_id',
        'bin_id',
        'lot_no',
        'serial_no',
        'expiration_date',
        'destination_zone_id',
        'destination_bin_id',
        'line_status',
        'handled_by',
        'handled_at',
    ];

    protected $casts = [
        'line_status' => PickLineStatus::class,
        'quantity' => 'decimal:4',
        'quantity_to_handle' => 'decimal:4',
        'quantity_handled' => 'decimal:4',
        'quantity_base' => 'decimal:4',
        'expiration_date' => 'date',
        'handled_at' => 'datetime',
    ];

    public function pick(): BelongsTo
    {
        return $this->belongsTo(WarehousePick::class, 'warehouse_pick_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'bin_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function destinationBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'destination_bin_id');
    }

    public function destinationZone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'destination_zone_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function remainingQuantity(): float
    {
        return max(0, (float) $this->quantity_to_handle - (float) $this->quantity_handled);
    }

    public function register(float $qty): void
    {
        if ($this->line_status->isTerminal()) {
            throw new \RuntimeException('Pick line is already terminal and cannot be registered.');
        }

        $newHandled = (float) $this->quantity_handled + $qty;

        $this->update([
            'quantity_handled' => $newHandled,
            'line_status' => $newHandled >= (float) $this->quantity_to_handle
                ? PickLineStatus::COMPLETED
                : PickLineStatus::IN_PROGRESS,
            'handled_by' => auth()->id(),
            'handled_at' => now(),
        ]);
    }
}
