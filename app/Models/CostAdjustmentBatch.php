<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostAdjustmentBatch extends Model
{
    protected $fillable = [
        'batch_number',
        'source_type',
        'source_id',
        'reason',
        'dry_run',
        'run_at',
        'run_by',
        'metadata',
    ];

    protected $casts = [
        'dry_run' => 'boolean',
        'run_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function runner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'run_by');
    }
}
