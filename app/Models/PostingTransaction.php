<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostingTransaction extends Model
{
    protected $fillable = [
        'business_id',
        'source_module',
        'source_type',
        'source_id',
        'source_number',
        'document_type',
        'document_number',
        'external_document_number',
        'transaction_key',
        'idempotency_key',
        'transaction_number',
        'posting_date',
        'document_date',
        'currency_code',
        'exchange_rate',
        'dimensions',
        'status',
        'actor_id',
        'reversal_of_transaction_id',
        'reason',
        'description',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'source_id' => 'integer',
        'transaction_number' => 'integer',
        'posting_date' => 'date',
        'document_date' => 'date',
        'exchange_rate' => 'decimal:8',
        'dimensions' => 'array',
        'actor_id' => 'integer',
        'reversal_of_transaction_id' => 'integer',
    ];

    public function glEntries(): HasMany
    {
        return $this->hasMany(GlEntry::class);
    }
}
