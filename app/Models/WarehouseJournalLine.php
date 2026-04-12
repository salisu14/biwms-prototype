<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JournalLineStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseJournalLine extends Model
{
    use HasFactory;

    protected $table = 'warehouse_journal_lines';

    protected $fillable = [
        'batch_id',
        'line_no',
        'posting_date',
        'entry_type',
        'document_no',
        'item_id',
        'description',
        'location_id',
        'zone_id',
        'bin_id',
        'source_location_id',
        'source_zone_id',
        'source_bin_id',
        'destination_location_id',
        'destination_zone_id',
        'destination_bin_id',
        'quantity',
        'quantity_base',
        'unit_of_measure_code',
        'lot_no',
        'serial_no',
        'source_lot_no',
        'source_serial_no',
        'destination_lot_no',
        'destination_serial_no',
        'warehouse_entry_id',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'quantity' => 'decimal:4',
        'quantity_base' => 'decimal:4',
        'qty_calculated' => 'decimal:4',
        'qty_physical' => 'decimal:4',
        'line_status' => JournalLineStatus::class,
        'expiration_date' => 'date',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(WarehouseJournalBatch::class, 'batch_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'source_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'bin_id');
    }

    public function warehouseActivity(): BelongsTo
    {
        return $this->belongsTo(WarehouseActivity::class, 'warehouse_activity_id');
    }

    public function warehouseActivityLine(): BelongsTo
    {
        return $this->belongsTo(WarehouseActivityLine::class, 'warehouse_activity_line_id');
    }

    public function warehouseEntry(): BelongsTo
    {
        return $this->belongsTo(WarehouseEntry::class, 'warehouse_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
