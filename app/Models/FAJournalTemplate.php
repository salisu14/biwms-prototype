<?php

namespace App\Models;

use App\Enums\SourceCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model for FA Journal Templates
 */
class FAJournalTemplate extends Model
{
    use HasFactory;

    /**
     * FIXED: Explicitly define the table name to prevent Laravel
     * from looking for "f_a_journal_templates".
     */
    protected $table = 'fa_journal_templates';

    protected $fillable = [
        'name', 'description', 'template_type', 'number_series_id',
        'posting_number_series_id', 'source_code', 'default_depreciation_book_id',
        'test_report_before_posting', 'is_active',
    ];

    protected $casts = [
        'test_report_before_posting' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function batches(): HasMany
    {
        return $this->hasMany(FAJournalBatch::class, 'template_id');
    }

    public function numberSeries(): BelongsTo
    {
        return $this->belongsTo(NumberSeries::class, 'number_series_id');
    }

    public function postingNumberSeries(): BelongsTo
    {
        return $this->belongsTo(NumberSeries::class, 'posting_number_series_id');
    }

    public function sourceCode(): BelongsTo
    {
        return $this->belongsTo(SourceCode::class, 'source_code');
    }

    public function defaultDepreciationBook(): BelongsTo
    {
        return $this->belongsTo(DepreciationBook::class, 'default_depreciation_book_id');
    }
}
