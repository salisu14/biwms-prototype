<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WarehouseDocumentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehousePick extends Model
{
    use HasFactory;

    protected $table = 'warehouse_picks';

    protected $fillable = [
        'no',
        'status',
        'location_id',
        'assigned_user_id',
        'source_document',
        'source_no',
        'source_id',
        'warehouse_shipment_id',
        'due_date',
        'started_at',
        'completed_at',
        'created_by',
        'remarks',
    ];

    protected $casts = [
        'status' => WarehouseDocumentStatus::class,
        'due_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function warehouseShipment(): BelongsTo
    {
        return $this->belongsTo(WarehouseShipment::class, 'warehouse_shipment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(WarehousePickLine::class, 'warehouse_pick_id')->orderBy('line_no');
    }

    public function openLines(): HasMany
    {
        return $this->lines()->where('line_status', 'open');
    }

    public function isComplete(): bool
    {
        return $this->lines()->where('line_status', '!=', 'completed')->doesntExist();
    }

    public function canRelease(): bool
    {
        return $this->status === WarehouseDocumentStatus::OPEN;
    }

    public function release(): void
    {
        if (! $this->canRelease()) {
            throw new \RuntimeException('Cannot release pick in status: '.$this->status->value);
        }

        $this->update(['status' => WarehouseDocumentStatus::RELEASED]);
    }

    public function register(): void
    {
        if (! $this->status->canProcess()) {
            throw new \RuntimeException('Pick must be released before registration.');
        }

        $this->update([
            'status' => WarehouseDocumentStatus::COMPLETED,
            'completed_at' => now(),
        ]);
    }
}
