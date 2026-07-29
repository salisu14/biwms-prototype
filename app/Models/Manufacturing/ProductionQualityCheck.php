<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Enums\ProductionQualityDisposition;
use App\Enums\ProductionQualityInspectionStage;
use App\Enums\ProductionQualityResult;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionQualityCheck extends Model
{
    protected $fillable = [
        'production_operation_execution_id',
        'stage',
        'result',
        'disposition',
        'checked_by',
        'checked_at',
        'measurements',
        'notes',
        'idempotency_key',
    ];

    protected $casts = [
        'stage' => ProductionQualityInspectionStage::class,
        'result' => ProductionQualityResult::class,
        'disposition' => ProductionQualityDisposition::class,
        'checked_at' => 'datetime',
        'measurements' => 'array',
    ];

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ProductionOperationExecution::class, 'production_operation_execution_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProductionQualityCheckAttachment::class, 'production_quality_check_id');
    }
}
