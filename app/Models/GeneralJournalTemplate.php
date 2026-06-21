<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeneralJournalTemplate extends Model
{
    use HasFactory;

    protected $table = 'general_journal_templates';

    protected $fillable = [
        'name',
        'description',
        'template_type',
        'number_series_id',
        'posting_number_series_id',
        'source_code',
        'reason_code',
        'default_balancing_account_id',
        'force_balancing_account',
        'copy_dimensions_from_batch',
        'suggest_balancing_amount',
        'check_amount_sign',
        'allowed_account_types',
        'mandatory_dimensions',
        'default_dimensions',
        'test_report_before_posting',
        'show_in_role_center',
        'is_active',
    ];

    protected $casts = [
        'force_balancing_account' => 'boolean',
        'copy_dimensions_from_batch' => 'boolean',
        'suggest_balancing_amount' => 'boolean',
        'check_amount_sign' => 'boolean',
        'test_report_before_posting' => 'boolean',
        'show_in_role_center' => 'boolean',
        'is_active' => 'boolean',
        'allowed_account_types' => 'array',
        'mandatory_dimensions' => 'array',
        'default_dimensions' => 'array',
    ];

    public function numberSeries(): BelongsTo
    {
        return $this->belongsTo(NumberSeries::class, 'number_series_id');
    }

    public function postingNumberSeries(): BelongsTo
    {
        return $this->belongsTo(NumberSeries::class, 'posting_number_series_id');
    }

    public function defaultBalancingAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_balancing_account_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(GeneralJournalBatch::class, 'template_id');
    }

    public function getNextDocumentNo(): string
    {
        return $this->numberSeries->getNextNo();
    }

    public function isRecurring(): bool
    {
        return $this->template_type === 'recurring';
    }

    public function isAllocation(): bool
    {
        return $this->template_type === 'allocation';
    }
}
