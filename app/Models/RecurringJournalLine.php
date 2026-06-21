<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecurringMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringJournalLine extends Model
{
    use HasFactory;

    protected $table = 'recurring_journal_lines';
    protected $fillable = [
        'recurring_method',
        'starting_date',
        'ending_date',
        'expiration_date',
        'posting_date',
        'account_id',
        'account_type',
        'balancing_account_id',
        'description',
        'amount',
        'calculation_formula',
        'account_to_calculate_balance',
        'percentage_for_balance',
        'shortcut_dimension_1_code',
        'shortcut_dimension_2_code',
        'dimension_set_entry',
        'use_allocation',
        'allocation_id',
        'source_code',
        'reason_code',
        'created_by',
        'last_posting_date',
        'next_posting_date',
        'posting_count',
        'line_status',
    ];

    protected $casts = [
        'starting_date' => 'date',
        'ending_date' => 'date',
        'expiration_date' => 'date',
        'posting_date' => 'date',
        'last_posting_date' => 'datetime',
        'next_posting_date' => 'date',
        'amount' => 'decimal:4',
        'use_allocation' => 'boolean',
        'percentage_for_balance' => 'decimal:2',
        'dimension_set_entry' => 'json',
        'recurring_method' => RecurringMethod::class,
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(RecurringJournalBatch::class, 'batch_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class, 'allocation_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
