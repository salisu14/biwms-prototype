<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_document',
        'source_no',
        'source_line_no',
        'source_id',
        'request_type',
        'location_id',
        'zone_id',
        'bin_id',
        'item_id',
        'quantity',
        'quantity_base',
        'unit_of_measure_code',
        'quantity_outstanding',
        'lot_no',
        'serial_no',
        'expiration_date',
        'status',
        'warehouse_activity_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'quantity_base' => 'decimal:4',
        'quantity_outstanding' => 'decimal:4',
        'expiration_date' => 'date',
    ];

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

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function warehouseActivity(): BelongsTo
    {
        return $this->belongsTo(WarehouseActivity::class, 'warehouse_activity_id');
    }

    public function isComplete(): bool
    {
        return $this->quantity_outstanding <= 0;
    }

    public function reduceOutstanding(float $quantity): void
    {
        $this->quantity_outstanding = max(0, $this->quantity_outstanding - $quantity);
        $this->status = $this->isComplete() ? 'completed' : ($this->quantity_outstanding < $this->quantity ? 'partial' : 'open');
        $this->save();
    }
}
