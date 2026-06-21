<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Job Journal Line — BC "Job Journal" domain extension.
 *
 * In BC, job journal lines extend the base journal line with
 * project-specific fields: Job No., Task No., entry type, quantity,
 * unit cost/price, and chargeability. The base financial data
 * (posting date, document no., account, amounts) lives on the
 * parent JournalLine record.
 *
 * @see https://learn.microsoft.com/en-us/dynamics365/business-central/design-details-job-journal
 */
class JobJournalLine extends Model
{
    use HasFactory;

    protected $table = 'job_journal_lines';

    protected $fillable = [
        'journal_line_id',
        'entry_type',
        'job_id',
        'job_task_no',
        'resource_id',
        'item_id',
        'gl_account_no',
        'quantity',
        'unit_of_measure_code',
        'total_cost',
        'total_price',
        'line_discount_percent',
        'line_discount_amount',
        'chargeable',
        'location_id',
        'bin_code',
        'work_type_code',
        'service_order_id',
        'description_2',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'total_price' => 'decimal:4',
        'line_discount_percent' => 'decimal:2',
        'line_discount_amount' => 'decimal:4',
    ];

    // --- Relationships ---

    /**
     * The parent base journal line carrying financial data
     * (posting date, document no., account, amounts, dimensions).
     */
    public function journalLine(): BelongsTo
    {
        return $this->belongsTo(JournalLine::class, 'journal_line_id');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'resource_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    // --- Helpers ---

    /** Total line amount net of discount. */
    public function getNetTotalCostAttribute(): float
    {
        return (float) $this->total_cost - (float) $this->line_discount_amount;
    }

    /** True when this line should appear on the project invoice. */
    public function isBillable(): bool
    {
        return in_array($this->chargeable, ['Billable', 'Both']);
    }
}
