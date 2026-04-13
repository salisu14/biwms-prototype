<?php

namespace App\Models;

use App\Enums\FAPostingType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model for FA Journal Lines
 */
class FAJournalLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id', 'line_no', 'fixed_asset_id', 'fa_no', 'posting_date',
        'fa_posting_type', 'document_no', 'description', 'amount',
        'calculated_amount', 'number_of_duplication', 'number_of_depreciation_days',
        'calculate_depreciation', 'index_factor', 'revaluation_amount',
        'disposal_proceeds', 'disposal_date', 'fa_posting_group_id',
        'override_account_id', 'shortcut_dimension_1_code',
        'shortcut_dimension_2_code', 'dimension_set_entry', 'line_status',
        'fa_ledger_entry_id', 'created_by',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'fa_posting_type' => FAPostingType::class,
        'amount' => 'decimal:4',
        'calculated_amount' => 'decimal:4',
        'number_of_depreciation_days' => 'decimal:4',
        'calculate_depreciation' => 'boolean',
        'index_factor' => 'decimal:6',
        'revaluation_amount' => 'decimal:4',
        'disposal_proceeds' => 'decimal:4',
        'disposal_date' => 'date',
        'dimension_set_entry' => 'json',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(FAJournalBatch::class, 'batch_id');
    }

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'fixed_asset_id');
    }

    public function postingGroup(): BelongsTo
    {
        return $this->belongsTo(FAPostingGroup::class, 'fa_posting_group_id');
    }
}
