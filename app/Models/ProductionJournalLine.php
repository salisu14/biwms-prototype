<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FlushingMethod;
use App\Enums\JournalLineStatus;
use App\Enums\ProductionJournalEntryType;
use App\Models\Manufacturing\MachineCenter;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\RoutingLine;
use App\Models\Manufacturing\WorkCenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionJournalLine extends Model
{
    protected $table = 'production_journal_lines';

    protected $fillable = [
        'batch_id',
        'line_no',
        'posting_date',
        'entry_type',
        'production_order_id',
        'production_order_no',
        'routing_line_no',
        'routing_line_id',
        'item_id',
        'item_no',
        'description',
        'unit_of_measure_code',
        'quantity',
        'quantity_base',
        'location_id',
        'zone_id',
        'bin_id',
        'lot_no',
        'serial_no',
        'expiration_date',
        'output_location_id',
        'output_bin_id',
        'work_center_id',
        'machine_center_id',
        'setup_time',
        'run_time',
        'stop_time',
        'output_quantity',
        'scrap_quantity',
        'direct_cost',
        'overhead_cost',
        'total_cost',
        'unit_cost',
        'flushing_method',
        'flushed',
        'flushed_at',
        'wip_account_id',
        'inventory_account_id',
        'direct_cost_account_id',
        'overhead_account_id',
        'shortcut_dimension_1_code',
        'shortcut_dimension_2_code',
        'dimension_set_entry',
        'source_code',
        'reason_code',
        'created_by',
        'line_status',
        'item_ledger_entry_id',
        'capacity_ledger_entry_id',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'expiration_date' => 'date',
        'flushed_at' => 'datetime',
        'quantity' => 'decimal:4',
        'quantity_base' => 'decimal:4',
        'setup_time' => 'decimal:4',
        'run_time' => 'decimal:4',
        'stop_time' => 'decimal:4',
        'direct_cost' => 'decimal:4',
        'overhead_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'flushing_method' => FlushingMethod::class,
        'flushed' => 'boolean',
        'dimension_set_entry' => 'array',
        'line_status' => JournalLineStatus::class,
        'entry_type' => ProductionJournalEntryType::class,
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductionJournalBatch::class, 'batch_id');
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function routingLine(): BelongsTo
    {
        return $this->belongsTo(RoutingLine::class, 'routing_line_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function machineCenter(): BelongsTo
    {
        return $this->belongsTo(MachineCenter::class, 'machine_center_id');
    }

    public function isFlushed(): bool
    {
        return $this->flushed;
    }

    public function canFlush(): bool
    {
        return ! $this->flushed && $this->flushing_method !== FlushingMethod::MANUAL;
    }

    public function getTotalTime(): float
    {
        return (float) $this->setup_time + (float) $this->run_time;
    }
}
