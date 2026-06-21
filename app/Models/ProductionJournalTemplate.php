<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionJournalTemplate extends Model
{
    use HasFactory;

    protected $table = 'production_journal_templates';

    protected $fillable = [
        'name',
        'description',
        'journal_type',
        'number_series_id',
        'posting_number_series_id',
        'source_code',
        'flushing_method_filter',
        'allow_flushing_override',
        'auto_post_output',
        'auto_post_consumption',
        'post_capacity',
        'post_time',
        'post_quantity',
        'absorb_overhead',
        'overhead_rate_source',
        'default_wip_account_id',
        'force_wip_account',
        'use_production_order_account_setup',
        'mandatory_dimensions',
        'default_dimensions',
        'copy_from_production_order',
        'consolidate_lines',
        'test_report_before_posting',
        'is_active',
    ];

    protected $casts = [
        'allow_flushing_override' => 'boolean',
        'auto_post_output' => 'boolean',
        'auto_post_consumption' => 'boolean',
        'post_capacity' => 'boolean',
        'post_time' => 'boolean',
        'post_quantity' => 'boolean',
        'absorb_overhead' => 'boolean',
        'force_wip_account' => 'boolean',
        'use_production_order_account_setup' => 'boolean',
        'copy_from_production_order' => 'boolean',
        'consolidate_lines' => 'boolean',
        'test_report_before_posting' => 'boolean',
        'is_active' => 'boolean',
        'mandatory_dimensions' => 'array',
        'default_dimensions' => 'array',
    ];

    public function isConsumption(): bool
    {
        return $this->journal_type === 'consumption';
    }

    public function isOutput(): bool
    {
        return $this->journal_type === 'output';
    }

    public function isCapacity(): bool
    {
        return $this->journal_type === 'capacity';
    }

    public function supportsFlushingMethod(string $method): bool
    {
        if ($this->flushing_method_filter === 'all') {
            return true;
        }

        return $this->flushing_method_filter === $method;
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductionJournalBatch::class, 'template_id');
    }

    public function numberSeries(): BelongsTo
    {
        return $this->belongsTo(NumberSeries::class, 'number_series_id');
    }

    public function postingNumberSeries(): BelongsTo
    {
        return $this->belongsTo(NumberSeries::class, 'posting_number_series_id');
    }

    public function defaultWipAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_wip_account_id');
    }
}
