<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model for FA Journal Batches
 */
class FAJournalBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id', 'name', 'description', 'assigned_user_id',
        'status', 'depreciation_book_id', 'posting_date', 'calculate_depreciation',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'calculate_depreciation' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(FAJournalTemplate::class, 'template_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FAJournalLine::class, 'batch_id');
    }

    public function depreciationBook(): BelongsTo
    {
        return $this->belongsTo(DepreciationBook::class);
    }
}
