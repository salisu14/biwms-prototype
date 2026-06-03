<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Recurring Journal Template — BC "Recurring General Journal" template.
 *
 * In BC, the Recurring Journal is a special journal type where lines
 * are NOT deleted after posting — they persist and are reposted
 * according to their recurring method and frequency formula
 * (e.g. "1M" = every month, "CM" = current-month-end, "1Q" = quarterly).
 *
 * The template defines:
 *   - Template code & description
 *   - Source code (for the audit trail / G/L register)
 *   - Posting number series
 *   - Whether a test report must be printed before posting
 *
 * Batches inherit these defaults and may override the no. series
 * and assigned user. Lines carry the recurring-specific fields
 * (method, frequency, expiration date) on RecurringJournalLine.
 *
 * Recurring Methods (BC standard):
 *   F  — Fixed (amount never changes)
 *   V  — Variable (user enters amount each period)
 *   B  — Balance (posts the account's current balance)
 *   RF — Reversing Fixed (posts & auto-reverses next period)
 *   RV — Reversing Variable
 *   RB — Reversing Balance
 */
class RecurringJournalTemplate extends Model
{
    use HasFactory;

    protected $table = 'recurring_journal_templates';

    protected $fillable = [
        'name',
        'description',
        'source_code',
        'posting_no_series_id',
        'reason_code',
        'test_report_before_posting',
        'copy_to_posted_lines',
        'is_active',
    ];

    protected $casts = [
        'test_report_before_posting' => 'boolean',
        'copy_to_posted_lines' => 'boolean',
        'is_active' => 'boolean',
    ];

    // --- Relationships ---

    public function batches(): HasMany
    {
        return $this->hasMany(RecurringJournalBatch::class, 'template_id');
    }

    /** Traverse template → batches → lines for reporting. */
    public function lines(): HasManyThrough
    {
        return $this->hasManyThrough(
            RecurringJournalLine::class,
            RecurringJournalBatch::class,
            'template_id',  // FK on batches
            'batch_id',     // FK on lines
        );
    }

    public function postingNumberSeries(): BelongsTo
    {
        return $this->belongsTo(NumberSeries::class, 'posting_no_series_id');
    }

    public function numberSeries(): BelongsTo
    {
        return $this->belongsTo(NumberSeries::class, 'posting_no_series_id');
    }

    public function defaultBalancingAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_balancing_account_id');
    }

    // --- Helpers ---

    /** All active lines due for posting today. */
    public function dueLines(): Collection
    {
        return $this->lines()
            ->where('line_status', 'active')
            ->where(fn ($q) => $q->whereNull('expiration_date')->orWhere('expiration_date', '>=', today()))
            ->get();
    }

    // --- Lifecycle ---

    /** Cascade-delete batches (and their lines) when a template is deleted. */
    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (RecurringJournalTemplate $template) {
            $template->batches()->each(fn (RecurringJournalBatch $batch) => $batch->delete());
        });
    }
}
