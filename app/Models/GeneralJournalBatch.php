<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JournalBatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeneralJournalBatch extends Model
{
    use HasFactory;

    protected $table = 'general_journal_batches';

    protected $fillable = [
        'template_id',
        'name',
        'description',
        'assigned_user_id',
        'status',
        'dimension_filter',
        'balancing_account_id',
        'reason_code',
        'copy_dimensions_from_line',
        'posting_date_restriction_from',
        'posting_date_restriction_to',
    ];

    protected $casts = [
        'status' => JournalBatchStatus::class,
        'dimension_filter' => 'array',
        'copy_dimensions_from_line' => 'boolean',
        'posting_date_restriction_from' => 'date',
        'posting_date_restriction_to' => 'date',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(GeneralJournalTemplate::class, 'template_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function balancingAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'balancing_account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GeneralJournalLine::class, 'batch_id');
    }

    public function openLines(): HasMany
    {
        return $this->lines()->where('line_status', 'open');
    }

    public function canPost(): bool
    {
        return $this->status === 'released' && $this->openLines()->exists();
    }

    public function totalDebits(): float
    {
        return $this->lines()->sum('debit_amount');
    }

    public function totalCredits(): float
    {
        return $this->lines()->sum('credit_amount');
    }

    public function isBalanced(): bool
    {
        return abs($this->totalDebits() - $this->totalCredits()) < 0.01;
    }

    public function release(): void
    {
        if ($this->status !== 'open') {
            throw new \RuntimeException('Only open batches can be released');
        }

        if (! $this->isBalanced()) {
            throw new \RuntimeException('Batch is not balanced');
        }

        $this->update(['status' => 'released']);
    }
}
