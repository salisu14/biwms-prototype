<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAssetJournalLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'line_no',
        'posting_date',
        'document_no',
        'asset_id',
        'fa_posting_type',
        'amount',
        'description',
        'created_by',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'amount' => 'decimal:4',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(FixedAssetJournalBatch::class, 'batch_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
