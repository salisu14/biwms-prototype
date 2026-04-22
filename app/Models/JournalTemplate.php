<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Journal Template — BC "Gen. Journal Template" equivalent.
 *
 * In BC every journal type (General, Item, Resource, Fixed Asset,
 * Cash Receipt, Payment, Job, Warehouse, Recurring) is governed by
 * a template that defines the journal's behaviour: its type, the
 * number series used for documents and posted entries, the default
 * balancing account, and audit-trail source codes.
 *
 * Batches (JournalBatch) belong to a template; lines (JournalLine)
 * belong to batches. Many Batch/Line-level defaults cascade from the
 * template when not explicitly overridden (via getEffective*).
 *
 * Template types handled by this generic model:
 *   General | Item | Resource | FixedAsset |
 *   CashReceipt | Payment | Job | Warehouse | Recurring
 *
 * Domain-specific template models (GeneralJournalTemplate,
 * ItemJournalTemplate, etc.) each have their own dedicated tables
 * and should be used for Filament resources. This generic model
 * serves the shared journal_templates table for cross-cutting
 * concerns and the JournalPostingService routing layer.
 */
class JournalTemplate extends Model
{
    use HasFactory;

    protected $table = 'journal_templates';

    protected $fillable = [
        'name',
        'description',
        'type',
        'recurring',
        'source_code',
        'no_series',
        'posting_no_series',
        'reason_code',
        'copy_vat_setup_to_lines',
        'allow_vat_difference',
        'bal_account_type',
        'bal_account_no',
        'page_id',
        'test_report_id',
        'posting_report_id',
        'copy_to_posted_jnl_lines',
    ];

    protected $casts = [
        'recurring' => 'boolean',
        'copy_vat_setup_to_lines' => 'boolean',
        'allow_vat_difference' => 'boolean',
        'copy_to_posted_jnl_lines' => 'boolean',
    ];

    // --- Relationships ---

    public function batches(): HasMany
    {
        return $this->hasMany(JournalBatch::class, 'journal_template_id');
    }

    /** Traverse template → batches → lines in one query. */
    public function lines(): HasManyThrough
    {
        return $this->hasManyThrough(JournalLine::class, JournalBatch::class);
    }

    // --- Helpers ---

    public function isRecurring(): bool
    {
        return $this->type === 'Recurring' || $this->recurring;
    }

    public function isGeneralType(): bool
    {
        return $this->type === 'General';
    }

    // --- Lifecycle ---

    /**
     * Cascade-delete batches (and their lines) when a template is deleted.
     * Matches BC behaviour: deleting a template removes all its batches.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (JournalTemplate $template) {
            $template->batches()->each(fn (JournalBatch $batch) => $batch->delete());
        });
    }
}
