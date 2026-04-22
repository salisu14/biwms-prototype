<?php

namespace App\Models;

use App\Enums\FAPostingType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Model for FA Journal Lines
 */
class FAJournalLine extends Model
{
    use HasFactory;

    /**
     * FIXED: Explicitly define the table name to prevent Laravel
     * from looking for "f_a_journal_lines".
     */
    protected $table = 'fa_journal_lines';

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
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function postingGroup(): BelongsTo
    {
        return $this->belongsTo(FAPostingGroup::class, 'fa_posting_group_id');
    }

    // Post based on FA posting type

    /**
     * @throws \Throwable
     */
    public function post()
    {
        DB::transaction(function() {
            $fa = $this->fixedAsset;
            $book = $fa->depreciationBooks()->where('code', $this->depreciation_book_code)->first();

            match($this->fa_posting_type) {
                'Acquisition' => $this->postAcquisition($fa, $book),
                'Depreciation' => $this->postDepreciation($fa, $book),
                'Write-Down' => $this->postWriteDown($fa, $book),
                'Appreciation' => $this->postAppreciation($fa, $book),
                'Disposal' => $this->postDisposal($fa, $book),
                default => throw new \Exception("Unknown FA posting type: {$this->fa_posting_type}"),
            };
        });
    }

    protected function postAcquisition($fa, $book)
    {
        // Update FA acquisition cost
        $book->increment('acquisition_cost', $this->amount);
        $book->update(['depreciation_start_date' => $this->journalLine->posting_date]);

        // Post to G/L: Debit FA Account, Credit AP/Cash
        $this->createFAGLEntries([
            'debit_account' => $book->fa_posting_group->acquisition_cost_account,
            'credit_account' => $this->journalLine->bal_account_no,
            'amount' => $this->amount,
        ]);
    }

    protected function postDepreciation($fa, $book)
    {
        $depreciationAmount = $this->depreciation_amount ?? $this->calculateDepreciation($fa, $book);

        // Update accumulated depreciation
        $book->increment('accumulated_depreciation', $depreciationAmount);

        // Post to G/L: Debit Depreciation Expense, Credit Accumulated Depreciation
        $this->createFAGLEntries([
            'debit_account' => $book->fa_posting_group->depreciation_expense_account,
            'credit_account' => $book->fa_posting_group->accumulated_depreciation_account,
            'amount' => $depreciationAmount,
        ]);
    }

    protected function calculateDepreciation($fa, $book)
    {
        return match($book->depreciation_method) {
            'Straight-Line' => $this->straightLineDepreciation($fa, $book),
            'Declining-Balance' => $this->decliningBalanceDepreciation($fa, $book),
            'DB1/SL' => $this->db1SlDepreciation($fa, $book),
            'Manual' => $this->amount,
            default => 0,
        };
    }

    protected function straightLineDepreciation($fa, $book)
    {
        $depreciableBase = $book->acquisition_cost - $book->salvage_value;
        $annualDepreciation = $depreciableBase / $book->depreciation_years;
        return round($annualDepreciation / 12, 4); // Monthly
    }
}
