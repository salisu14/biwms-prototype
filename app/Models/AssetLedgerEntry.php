<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetLedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'posting_date',
        'document_no',
        'entry_type', // Acquisition, Depreciation, Disposal, etc.
        'amount',
        'amount_lcy',
        'currency_id',
        'description',
        'user_id',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'amount' => 'decimal:4',
        'amount_lcy' => 'decimal:4',
        'currency_id' => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
