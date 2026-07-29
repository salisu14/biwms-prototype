<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Enums\ProductionScrapPostingTreatment;
use App\Enums\ProductionScrapStage;
use Illuminate\Database\Eloquent\Model;

class ProductionScrapReason extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'stage',
        'default_posting_treatment',
        'requires_approval',
        'requires_quality_review',
        'recoverable',
        'reworkable',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'stage' => ProductionScrapStage::class,
        'default_posting_treatment' => ProductionScrapPostingTreatment::class,
        'requires_approval' => 'boolean',
        'requires_quality_review' => 'boolean',
        'recoverable' => 'boolean',
        'reworkable' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
