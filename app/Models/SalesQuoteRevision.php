<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesQuoteRevision extends Model
{
    use SoftDeletes; // optional, allows undoing/deleting revisions safely

    protected $fillable = [
        'revision_number',
        'sales_quote_id',
        'changes',
        'version',
        'description',
        'revision_date',
    ];

    protected $casts = [
        'changes' => 'array',             // JSON → PHP array
        'revision_date' => 'datetime',    // automatic Carbon instance
        'version' => 'integer',           // numeric version
    ];

    protected static function booted()
    {
        static::creating(function ($revision) {
            // auto-versioning per quote
            if (! $revision->version) {
                $revision->version = self::where('sales_quote_id', $revision->sales_quote_id)->max('version') + 1;
            }

            // auto-set revision_date if not provided
            if (! $revision->revision_date) {
                $revision->revision_date = now();
            }
        });
    }

    // Relations
    public function salesQuote(): BelongsTo
    {
        return $this->belongsTo(SalesQuote::class);
    }

    // Optional helper to summarize changed fields
    public function getChangesSummaryAttribute(): string
    {
        return implode(', ', array_keys($this->changes ?? []));
    }
}
