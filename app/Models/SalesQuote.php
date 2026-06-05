<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use App\Services\NumberSeriesService;
use App\Services\Sales\SalesQuoteService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesQuote extends Model
{
    protected static function booted(): void
    {
        static::creating(function (SalesQuote $quote): void {
            if (empty($quote->quote_no)) {
                $quote->quote_no = self::generateQuoteNumber();
            }
        });

        static::updated(function (SalesQuote $quote): void {
            $changes = $quote->getChanges();

            unset($changes['updated_at']);

            if (! empty($changes)) {
                $quote->revisions()->create([
                    'revision_number' => 'REV-'.strtoupper(uniqid()),
                    'changes' => $changes,
                    'description' => 'System captured changes.',
                ]);
            }
        });
    }

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

    public static function generateQuoteNumber(): string
    {
        $seriesService = app(NumberSeriesService::class);

        foreach (['S-QUOTE', 'SALES_QUOTE', 'SQ'] as $seriesCode) {
            $nextNumber = $seriesService->tryGetNextNo($seriesCode);

            if (! empty($nextNumber)) {
                return $nextNumber;
            }
        }

        $year = date('Y');
        $sequence = static::whereYear('created_at', $year)->count() + 1;

        return sprintf('SQ-%d-%06d', $year, $sequence);
    }
}
