<?php

namespace App\Models;

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

    public function defaultDepreciationBook(): BelongsTo
    {
        return $this->belongsTo(DepreciationBook::class, 'default_depreciation_book_id');
    }
}
