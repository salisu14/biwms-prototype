<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use App\Services\Sales\SalesQuoteService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesQuote extends Model
{
    protected $fillable = [
        'quote_no',
        'customer_id',
        'quote_date',
        'valid_until',
        'total_amount',
        'status',
        'approval_status',
        'approved_by',
        'approved_at',
        'is_price_inclusive',
    ];

    protected $casts = [
        'quote_date' => 'date',
        'valid_until' => 'date',
        'total_amount' => 'decimal:2',
        'status' => QuoteStatus::class, // Casting to our Enum
    ];

    /**
     * Get the customer that owns the quote.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the line items for the quote.
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalesQuoteItem::class);
    }

    public function revisions()
    {
        return $this->hasMany(SalesQuoteRevision::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Recalculate the grand total of the quote based on line items.
     */
    public function refreshTotal(): void
    {
        $this->update([
            'total_amount' => $this->items()->sum('line_total'),
        ]);
    }

    public function canConvertToOrder(): bool
    {
        return app(SalesQuoteService::class)->canConvertToOrder($this);
    }

    public function convertToOrder(): SalesOrder
    {
        return app(SalesQuoteService::class)->convertToOrder($this);
    }

    protected static function booted()
    {
        static::updated(function (SalesQuote $quote) {
            // In the updated callback, getChanges() reflects the persisted delta.
            $changes = $quote->getChanges();

            // Remove timestamps from the log so it stays clean
            unset($changes['updated_at']);

            if (! empty($changes)) {
                $quote->revisions()->create([
                    'revision_number' => 'REV-'.strtoupper(uniqid()),
                    'changes' => $changes,
                    'description' => 'System captured changes.',
                    // 'version' and 'revision_date' are handled by Revision model boot
                ]);
            }
        });
    }
}
