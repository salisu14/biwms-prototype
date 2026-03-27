<?php
// app/Models/DocumentHeader.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'doc_type',
    'doc_no',
    'doc_date',
    'posting_date',
    'status',
    'created_by',
    'notes'
])]
class DocumentHeader extends Model
{
    use HasFactory;

    protected $table = 'document_headers';
    protected $casts = [
        'doc_date' => 'date',
        'posting_date' => 'date',
    ];

    /**
     * User who created this document
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Line items (ledger entries) for this document
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(ItemLedger::class, 'doc_id');
    }

    /**
     * Total value of this document (sum of all line costs)
     */
    public function getTotalValueAttribute(): float
    {
        return $this->ledgerEntries()
            ->selectRaw('SUM(quantity * unit_cost) as total')
            ->value('total') ?? 0;
    }

    /**
     * Post the document (change status and create ledger entries)
     */
    public function post(): bool
    {
        if ($this->status !== 'OPEN') {
            return false;
        }

        // Transaction: update status and ensure all ledger entries are valid
        \DB::transaction(function () {
            $this->update(['status' => 'POSTED']);
            // Additional posting logic here
        });

        return true;
    }
}
