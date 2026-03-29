<?php

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAssetDepreciationLedger extends Model
{
    use HasFactory;

    protected $table = 'fixed_asset_depreciation_ledger';

    protected $fillable = [
        'fixed_asset_id',
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
        'depreciation_amount' => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'net_book_value' => 'decimal:2',
        'posted' => 'boolean',
    ];

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }
}
