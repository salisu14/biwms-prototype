<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JournalLineStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class WarehouseJournalLine extends Model
{
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

    // Register (not post) - only creates warehouse entries
    public function register()
    {
        DB::transaction(function() {
            // Update bin contents
            if ($this->entry_type === 'Movement') {
                $this->moveBetweenBins();
            } else {
                $this->adjustBinQuantity();
            }

            // Create warehouse entry
            WarehouseEntry::create([
                'item_id' => $this->item_id,
                'location_id' => $this->location_id,
                'zone_code' => $this->zone_code,
                'bin_code' => $this->to_bin_code ?? $this->from_bin_code,
                'quantity' => $this->quantity,
                'entry_type' => $this->entry_type,
                'posting_date' => $this->journalLine->posting_date,
                'registering_employee_id' => $this->registering_employee_id,
                'source_document' => $this->whse_document_type,
                'source_no' => $this->whse_document_no,
            ]);

            $this->update(['registering_date' => now()]);
        });
    }

    protected function moveBetweenBins()
    {
        // Decrease from bin
        BinContent::where([
            'bin_id' => $this->getBinId($this->from_bin_code),
            'item_id' => $this->item_id,
        ])->decrement('quantity', $this->quantity);

        // Increase to bin
        BinContent::updateOrCreate(
            [
                'bin_id' => $this->getBinId($this->to_bin_code),
                'item_id' => $this->item_id,
            ],
            ['quantity' => DB::raw("quantity + {$this->quantity}")]
        );
    }
}
