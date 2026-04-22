<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JournalLineStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GeneralJournalLine extends Model
{
    protected $table = 'general_journal_lines';

    protected $fillable = [
        'batch_id',
        'line_no',
        'posting_date',
        'document_type',
        'document_no',
        'external_document_no',
        'account_id',
        'account_type',
        'balancing_account_id',
        'description',
        'debit_amount',
        'credit_amount',
        'amount_lcy',
        'currency_code',
        'currency_factor',
        'amount_currency',
        'shortcut_dimension_1_code',
        'shortcut_dimension_2_code',
        'dimension_set_entry',
        'business_unit_id',
        'source_code',
        'reason_code',
        'comment',
        'created_by',
        'line_status',
        'posted_entry_id',
        'posted_entry_type',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'debit_amount' => 'decimal:4',
        'credit_amount' => 'decimal:4',
        'amount_lcy' => 'decimal:4',
        'currency_factor' => 'decimal:6',
        'amount_currency' => 'decimal:4',
        'dimension_set_entry' => 'array',
        'line_status' => JournalLineStatus::class,
        'created_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(GeneralJournalBatch::class, 'batch_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function balancingAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'balancing_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function postedEntry(): MorphTo
    {
        return $this->morphTo('posted_entry', 'posted_entry_type', 'posted_entry_id');
    }

    public function getNetAmount(): float
    {
        return (float) $this->debit_amount - (float) $this->credit_amount;
    }

    public function isDebit(): bool
    {
        return $this->debit_amount > 0;
    }

    public function isCredit(): bool
    {
        return $this->credit_amount > 0;
    }

    public function validate(): array
    {
        $errors = [];

        if (! $this->posting_date) {
            $errors[] = 'Posting date is required';
        }

        if (! $this->account_id) {
            $errors[] = 'Account is required';
        }

        if ($this->debit_amount == 0 && $this->credit_amount == 0) {
            $errors[] = 'Either debit or credit amount must be specified';
        }

        if ($this->debit_amount != 0 && $this->credit_amount != 0) {
            $errors[] = 'Cannot have both debit and credit amounts';
        }

        // Check mandatory dimensions from template
        $template = $this->batch->template;
        foreach ($template->mandatory_dimensions ?? [] as $dim) {
            if (empty($this->dimension_set_entry[$dim])) {
                $errors[] = "Dimension {$dim} is mandatory";
            }
        }

        return $errors;
    }

    // Create reversing entry after posting
    public function createReversingEntry()
    {
        if ($this->entry_type !== 'Reversing') return null;

        $reversingLine = $this->journalLine->replicate();
        $reversingLine->debit_amount = $this->journalLine->credit_amount;
        $reversingLine->credit_amount = $this->journalLine->debit_amount;
        $reversingLine->posting_date = $this->reversal_date;
        $reversingLine->description = "Reversal of {$this->journalLine->document_no}";
        $reversingLine->save();

        return $reversingLine;
    }

    // Generate next recurring line
    public function generateNextRecurring()
    {
        if ($this->entry_type !== 'Recurring') return null;
        if ($this->expiration_date && now()->gt($this->expiration_date)) return null;

        $nextDate = $this->calculateNextDate($this->journalLine->posting_date, $this->recurring_frequency);

        $nextLine = $this->journalLine->replicate();
        $nextLine->posting_date = $nextDate;
        $nextLine->document_no = null; // Get new number
        $nextLine->status = 'Open';
        $nextLine->posted_at = null;
        $nextLine->posted_document_no = null;
        $nextLine->save();

        // Create associated general journal line
        GeneralJournalLine::create([
            'journal_line_id' => $nextLine->id,
            'entry_type' => 'Recurring',
            'recurring_frequency' => $this->recurring_frequency,
            'expiration_date' => $this->expiration_date,
        ]);

        return $nextLine;
    }

    protected function calculateNextDate($currentDate, $frequency)
    {
        // Parse BC date formula: 1M, 1D, 1Y, 1W, CM (current month), CQ (current quarter)
        $number = (int) preg_replace('/[^0-9]/', '', $frequency);
        $unit = preg_replace('/[0-9]/', '', $frequency);

        return match($unit) {
            'D' => $currentDate->copy()->addDays($number),
            'W' => $currentDate->copy()->addWeeks($number),
            'M' => $currentDate->copy()->addMonths($number),
            'Y' => $currentDate->copy()->addYears($number),
            'CM' => $currentDate->copy()->endOfMonth(),
            'CQ' => $currentDate->copy()->endOfQuarter(),
            default => $currentDate->copy()->addMonth(),
        };
    }
}
