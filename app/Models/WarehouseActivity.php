<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WarehouseActivityType;
use App\Enums\WarehouseDocumentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseActivity extends Model
{
    use HasFactory;

    protected $table = 'warehouse_activities';

    protected $fillable = [
        'no',
        'activity_type',
        'status',
        'location_id',
        'zone_id',
        'bin_id',
        'source_document',
        'source_no',
        'source_line_no',
        'source_id',
        'assigned_user_id',
        'started_at',
        'completed_at',
        'remarks',
    ];

    protected $casts = [
        'activity_type' => WarehouseActivityType::class,
        'status' => WarehouseDocumentStatus::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
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

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(WarehouseActivityLine::class, 'warehouse_activity_id');
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
            throw new \RuntimeException('Cannot release activity in status: '.$this->status->value);
        }

        $this->update(['status' => WarehouseDocumentStatus::RELEASED]);
    }
}
