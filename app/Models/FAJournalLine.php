<?php

namespace App\Models;

use App\Enums\FAPostingType;
use App\Models\DepreciationBook;
use App\Services\FixedAsset\FAPostingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
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

    /**
     * The "booted" method of the model.
     * Automatically handles the assignment of the creator's ID.
     */
    protected static function booted(): void
    {
        static::creating(function (FAJournalLine $line) {
            if (Auth::check()) {
                $line->created_by = Auth::id();
            }
        });
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(FAJournalBatch::class, 'batch_id');
    }

    public function depreciationBook(): BelongsTo
    {
        // If the journal lines table stores a code column, use it; otherwise
        // fall back to attempting to use an integer FK if present. If neither
        // exists, the relation will be resolved via the asset at runtime.
        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'depreciation_book_code')) {
                return $this->belongsTo(DepreciationBook::class, 'depreciation_book_code', 'code');
            }
        } catch (\Throwable $e) {
            // Schema inspection may fail in some test contexts; ignore and continue
        }

        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'depreciation_book_id')) {
                return $this->belongsTo(DepreciationBook::class, 'depreciation_book_id');
            }
        } catch (\Throwable $e) {
        }

        // No direct FK on lines table; return a dummy relation to allow callers
        // to call ->first() safely (will be null). We rely on FixedAsset relation
        // as a fallback in posting logic.
        return $this->belongsTo(DepreciationBook::class, 'depreciation_book_id');
    }

    /**
     * Backwards-compatible virtual attribute: allow callers (and Filament)
     * to set `depreciation_book_id` or `depreciation_book_code`. If the
     * corresponding column does not exist on the table, do not set an
     * attribute (to avoid SQL errors) and cache the value in-memory so
     * other code can read it during the request lifecycle.
     */
    protected ?string $tempDepreciationBookCode = null;
    protected ?int $tempDepreciationBookId = null;

    public function setDepreciationBookIdAttribute($value): void
    {
        // If table has integer FK column, set it normally
        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'depreciation_book_id')) {
                $this->attributes['depreciation_book_id'] = $value;
                return;
            }
        } catch (\Throwable $e) {
        }

        // Otherwise, attempt to resolve to a code and store in-memory only
        if (empty($value)) {
            $this->tempDepreciationBookId = null;
            $this->tempDepreciationBookCode = null;
            return;
        }

        if (is_numeric($value)) {
            $book = DepreciationBook::find((int) $value);
            $this->tempDepreciationBookId = (int) $value;
            $this->tempDepreciationBookCode = $book?->code ?? null;
        } else {
            $this->tempDepreciationBookId = null;
            $this->tempDepreciationBookCode = (string) $value;
        }
    }

    public function setDepreciationBookCodeAttribute($value): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'depreciation_book_code')) {
                $this->attributes['depreciation_book_code'] = $value;
                return;
            }
        } catch (\Throwable $e) {
        }

        $this->tempDepreciationBookCode = $value ? (string) $value : null;
    }

    public function getDepreciationBookIdAttribute(): ?int
    {
        // Prefer real column if present
        try {
            if (isset($this->attributes['depreciation_book_id'])) {
                return $this->attributes['depreciation_book_id'];
            }
            if (isset($this->attributes['depreciation_book_code'])) {
                $code = $this->attributes['depreciation_book_code'];
                $book = DepreciationBook::where('code', $code)->first();
                return $book?->id ?? null;
            }
        } catch (\Throwable $e) {
        }

        // Fall back to the temporary cached values
        if ($this->tempDepreciationBookId) {
            return $this->tempDepreciationBookId;
        }
        if ($this->tempDepreciationBookCode) {
            $book = DepreciationBook::where('code', $this->tempDepreciationBookCode)->first();
            return $book?->id ?? null;
        }

        // As final fallback, try to use the asset's depreciation book
        return $this->fixedAsset?->depreciation_book_id ?? null;
    }

    public function getDepreciationBookCodeAttribute(): ?string
    {
        try {
            if (isset($this->attributes['depreciation_book_code'])) {
                return $this->attributes['depreciation_book_code'];
            }
        } catch (\Throwable $e) {
        }

        if ($this->tempDepreciationBookCode) {
            return $this->tempDepreciationBookCode;
        }

        // Fallback to asset's book code when possible
        return $this->fixedAsset?->depreciationBook?->code ?? null;
    }

    public function journalLine(): BelongsTo
    {
        return $this->belongsTo(GeneralJournalLine::class, 'journal_line_id');
    }

    public function faLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(FALedgerEntry::class, 'fa_ledger_entry_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function overrideAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'override_account_id');
    }

    public function shortcutDimension1(): BelongsTo
    {
        return $this->belongsTo(Dimension::class, 'shortcut_dimension_1_code');
    }

    public function shortcutDimension2(): BelongsTo
    {
        return $this->belongsTo(Dimension::class, 'shortcut_dimension_2_code');
    }

    public function dimensionSetEntry(): BelongsTo
    {
        return $this->belongsTo(DimensionSetEntry::class, 'dimension_set_entry_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FAJournalTemplate::class, 'template_id');
    }

//    public function depreciationBook(): BelongsTo
//    {
//        return $this->belongsTo(DepreciationBook::class, 'depreciation_book_code');
//    }

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
    public function post(): void
    {
        DB::transaction(function() {
            $fa = $this->fixedAsset;
            // Determine the depreciation book to use:
            // - prefer the code set on the journal line (`depreciation_book_code`),
            // - fall back to the asset's `depreciationBook` relation.
            $book = null;
            if (! empty($this->depreciation_book_code)) {
                $book = DepreciationBook::where('code', $this->depreciation_book_code)->first();
            }

            if (! $book) {
                $book = $fa->depreciationBook;
            }

            if (! $book) {
                throw new \RuntimeException("No depreciation book found for FA journal line {$this->line_no} (asset {$fa?->fa_no}).");
            }

            match($this->fa_posting_type) {
                FAPostingType::ACQUISITION => $this->postAcquisition($fa, $book),
                FAPostingType::DEPRECIATION => $this->postDepreciation($fa, $book),
                FAPostingType::WRITE_DOWN => $this->postWriteDown($fa, $book),
                FAPostingType::APPRECIATION => $this->postAppreciation($fa, $book),
                FAPostingType::DISPOSAL => $this->postDisposal($fa, $book),
                default => throw new \Exception(sprintf('Unknown FA posting type: %s', $this->fa_posting_type->value ?? (string) $this->fa_posting_type)),
            };
        });
    }

    protected function postAcquisition($fa, $book): void
    {
        // Update FA acquisition cost on the asset (denormalized fields live on FixedAsset)
        $fa->increment('acquisition_cost', $this->amount);
        $fa->update(['depreciation_starting_date' => $this->posting_date ?? $this->journalLine?->posting_date ?? now()]);
        // Create FA ledger + G/L entries via the central posting service
        $postingService = app(FAPostingService::class);
        $postingService->postEntry(
            $fa,
            $this->fa_posting_type,
            (float) $this->amount,
            $this->description ?? '',
            $this->document_no ?? null,
            $this->posting_date?->toDateTime() ?? now(),
            ['journal_batch_id' => $this->batch_id ?? null, 'document_line_no' => $this->line_no ?? null]
        );
    }

    protected function postDepreciation($fa, $book): void
    {
        $depreciationAmount = $this->depreciation_amount ?? $this->calculateDepreciation($fa, $book);
        // Update accumulated depreciation on the asset
        $fa->increment('accumulated_depreciation', $depreciationAmount);
        // Create FA ledger + G/L entries via the central posting service
        $postingService = app(FAPostingService::class);
        $postingService->postEntry(
            $fa,
            $this->fa_posting_type,
            (float) $depreciationAmount,
            $this->description ?? '',
            $this->document_no ?? null,
            $this->posting_date?->toDateTime() ?? now(),
            ['journal_batch_id' => $this->batch_id ?? null, 'document_line_no' => $this->line_no ?? null]
        );
    }

    protected function calculateDepreciation($fa, $book)
    {
        // If an explicit amount is provided on the journal line, use it
        if (! empty($this->amount) && (float) $this->amount > 0) {
            return (float) $this->amount;
        }
        // Use the asset's configured depreciation method and useful life where appropriate
        return match((string) $fa->depreciation_method) {
            'Straight-Line', 'straight_line' => $this->straightLineDepreciation($fa, $book),
            'Declining-Balance', 'declining_balance' => $this->decliningBalanceDepreciation($fa, $book),
            'DB1/SL', 'db1_sl' => $this->db1SlDepreciation($fa, $book),
            'Manual', 'manual' => $this->amount,
            default => 0,
        };
    }

    protected function straightLineDepreciation($fa, $book): float
    {
        $depreciableBase = ($fa->acquisition_cost ?? 0) - ($fa->salvage_value ?? 0);
        $years = $fa->useful_life_years ?: 1;
        $annualDepreciation = $depreciableBase / $years;
        return round($annualDepreciation / 12, 4); // Monthly
    }
}
