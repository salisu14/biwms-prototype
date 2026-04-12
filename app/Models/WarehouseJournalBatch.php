<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JournalBatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseJournalBatch extends Model
{
    use HasFactory;

    protected $table = 'warehouse_journal_batches';

    protected $fillable = [
        'template_id',
        'name',
        'description',
        'status',
        'reason_code',
        'assigned_user_id',
        'location_id',
        'zone_id',
        'journal_type',
        'dimension_filter',
        'copy_from_warehouse_activity',
    ];

    protected $casts = [
        'status' => JournalBatchStatus::class,
        'dimension_filter' => 'array',
        'copy_from_warehouse_activity' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(WarehouseJournalTemplate::class, 'template_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(WarehouseJournalLine::class, 'batch_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }
}
