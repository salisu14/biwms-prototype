<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FAPostingType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FALedgerEntry extends Model
{
    use HasFactory;

    protected $table = 'fa_ledger_entries';

    protected $fillable = [
        'fixed_asset_id',
        'depreciation_book_id',
        'fa_posting_type',
        'entry_no',
        'document_type',
        'document_no',
        'document_line_no',
        'posting_date',
        'gl_entry_id',
        'amount',
        'amount_lcy',
        'depreciation_amount',
        'accumulated_depreciation',
        'book_value_after',
        'number_of_depreciation_days',
        'depreciation_period',
        'revaluation_amount',
        'index_factor',
        'proceeds_on_disposal',
        'gain_loss_on_disposal',
        'description',
        'comment',
        'source_code',
        'journal_batch_id',
        'journal_batch_type',
        'created_by',
        'entry_timestamp',
        'reversed_entry_id',
        'reversed',
        'reversed_at',
    ];

    protected $casts = [
        'fa_posting_type' => FAPostingType::class,
        'posting_date' => 'date',
        'amount' => 'decimal:4',
        'amount_lcy' => 'decimal:4',
        'depreciation_amount' => 'decimal:4',
        'accumulated_depreciation' => 'decimal:4',
        'book_value_after' => 'decimal:4',
        'revaluation_amount' => 'decimal:4',
        'index_factor' => 'decimal:6',
        'proceeds_on_disposal' => 'decimal:4',
        'gain_loss_on_disposal' => 'decimal:4',
        'entry_timestamp' => 'datetime',
        'reversed' => 'boolean',
        'reversed_at' => 'datetime',
    ];

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function depreciationBook(): BelongsTo
    {
        return $this->belongsTo(DepreciationBook::class, 'depreciation_book_id');
    }

    public function reversedEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_entry_id');
    }

    public function isAcquisition(): bool
    {
        return $this->fa_posting_type === FAPostingType::ACQUISITION;
    }

    public function isDepreciation(): bool
    {
        return $this->fa_posting_type === FAPostingType::DEPRECIATION;
    }

    public function isDisposal(): bool
    {
        return $this->fa_posting_type === FAPostingType::DISPOSAL;
    }

    public function reverse(): void
    {
        if ($this->reversed) {
            throw new \RuntimeException('Entry already reversed');
        }

        $this->update([
            'reversed' => true,
            'reversed_at' => now(),
        ]);

        // Create reversing entry with opposite amounts
        // Implementation in service layer
    }
}
