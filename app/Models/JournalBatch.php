<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Journal Batch — BC "Gen. Journal Batch" equivalent.
 *
 * A batch is a named working area within a journal template. In BC,
 * multiple users or periods can have their own batch ("DEFAULT",
 * "PAYABLES", "JANUARY") without interfering with each other.
 *
 * Batch-level fields (balancing account, no. series, reason code)
 * override the template's defaults when set. The getEffective*
 * helpers implement this cascade logic.
 *
 * Each batch contains JournalLine records which are posted in one
 * atomic operation via JournalPostingService.
 */
class JournalBatch extends Model
{
    use HasFactory;

    protected $table = 'journal_batches';

    protected $fillable = [
        'journal_template_id',
        'name',
        'description',
        'user_id',
        'bal_account_type',
        'bal_account_no',
        'no_series',
        'posting_no_series',
        'reason_code',
        'recurring',
    ];

    protected $casts = [
        'recurring' => 'boolean',
    ];

    // --- Relationships ---

    public function template(): BelongsTo
    {
        return $this->belongsTo(JournalTemplate::class, 'journal_template_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'journal_batch_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // --- Helpers ---

    /**
     * Return the batch-level value if set, otherwise fall back to
     * the parent template's value — mirrors BC's property inheritance.
     */
    public function getEffective(string $attribute): mixed
    {
        return $this->$attribute ?? $this->template?->$attribute;
    }

    /** Sum of all debit amounts across open lines. */
    public function totalDebits(): float
    {
        return (float) $this->lines()->sum('debit_amount');
    }

    /** Sum of all credit amounts across open lines. */
    public function totalCredits(): float
    {
        return (float) $this->lines()->sum('credit_amount');
    }

    /** True when total debits equal total credits (within 0.01 tolerance). */
    public function isBalanced(): bool
    {
        return abs($this->totalDebits() - $this->totalCredits()) < 0.01;
    }

    // --- Scopes ---

    /** Lines in scope for the given user's personal batch. */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    // --- Lifecycle ---

    /** Cascade-delete lines when a batch is deleted. */
    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (JournalBatch $batch) {
            $batch->lines()->delete();
        });
    }
}
