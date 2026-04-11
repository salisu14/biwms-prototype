<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDepreciationLedger extends Model
{
    use HasFactory;

    protected $table = 'asset_depreciation_ledger';

    protected $fillable = [
        'asset_id',
        'depreciation_date',
        'depreciation_period',
        'depreciation_amount',
        'accumulated_depreciation',
        'net_book_value',
        'posted_document_no',
        'posted',
    ];

    protected $casts = [
        'depreciation_date' => 'date',
        'depreciation_amount' => 'decimal:4',
        'accumulated_depreciation' => 'decimal:4',
        'net_book_value' => 'decimal:4',
        'posted' => 'boolean',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
