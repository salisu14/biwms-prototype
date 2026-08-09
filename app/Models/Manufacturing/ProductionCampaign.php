<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Enums\ProductionCampaignStatus;
use App\Models\Business;
use App\Models\Location;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionCampaign extends Model
{
    protected $fillable = [
        'business_id',
        'location_id',
        'work_center_id',
        'code',
        'name',
        'status',
        'grouping_key',
        'grouping_rule',
        'planned_start_at',
        'planned_end_at',
        'sequence',
        'setup_reduction_percent',
        'changeover_notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductionCampaignStatus::class,
            'planned_start_at' => 'datetime',
            'planned_end_at' => 'datetime',
            'sequence' => 'integer',
            'setup_reduction_percent' => 'decimal:4',
            'metadata' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ProductionCampaignOrder::class);
    }
}
