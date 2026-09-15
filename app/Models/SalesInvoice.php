<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Services\Business\BusinessContextService;
use App\Services\Sales\SalesDocumentCurrencyService;
use App\Services\Sales\SalesDocumentMonetaryCalculator;
use App\Support\DecimalPrecision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesInvoice extends Model
{
    protected $fillable = [
        'business_id',
        'invoice_number',
        'customer_id',
        'sales_order_id',
        'total_amount',
        'total_amount_lcy',
        'currency_code',
        'currency_factor',
        'currency_id',
        'status',
        'posted_at',
        'posted_by',
        'invoice_date',
        'due_date',
        //        'dimension_1_id',
        //        'dimension_2_id',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'status' => ApprovalStatus::class,
        'invoice_date' => 'date',
        'due_date' => 'date',
        'posted_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'total_amount_lcy' => 'decimal:2',
        'currency_factor' => 'decimal:6',
        'currency_id' => 'integer',
        'business_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (SalesInvoice $invoice): void {
            $invoice->business_id ??= $invoice->sales_order_id
                ? SalesOrder::query()->whereKey($invoice->sales_order_id)->value('business_id')
                : app(BusinessContextService::class)->resolveId();
        });

        // An explicit currency/rate change must resolve through the shared
        // Sales currency contract and fail closed before persistence, so a valid
        // foreign draft cannot transition to a malformed context while keeping
        // stale LCY. An unrelated edit to a historical row is left untouched.
        static::updating(function (SalesInvoice $invoice): void {
            if (! $invoice->isDirty('currency_code') && ! $invoice->isDirty('currency_factor')) {
                return;
            }

            $factorIsDirty = $invoice->isDirty('currency_factor');

            $currencyContext = app(SalesDocumentCurrencyService::class)->resolveForExistingDocument(
                $invoice->currency_code,
                $factorIsDirty ? $invoice->currency_factor : null,
            );

            $invoice->currency_code = $currencyContext['currency_code'];
            $invoice->currency_factor = $currencyContext['currency_factor'];
        });

        // A currency/rate change refreshes the LCY equivalents only; it never
        // re-resolves the commercial document-currency line prices.
        static::updated(function (SalesInvoice $invoice): void {
            if ($invoice->wasChanged('currency_code') || $invoice->wasChanged('currency_factor')) {
                $invoice->resyncLcyForCurrencyChange();
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesInvoiceLine::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function isPosted(): bool
    {
        return $this->status === ApprovalStatus::POSTED || $this->posted_at !== null;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function refreshTotal(): void
    {
        $this->update([
            'total_amount' => $this->lines()->sum('line_total'),
            'total_amount_lcy' => $this->sumLineLcy('line_total_lcy'),
        ]);
    }

    /**
     * Recompute each line's LCY equivalents and the header LCY total after the
     * document currency/rate changes.
     */
    public function resyncLcyForCurrencyChange(): void
    {
        $lines = $this->lines()->get();

        foreach ($lines as $line) {
            $line->setRelation('salesInvoice', $this);
            $line->deriveLcyAmounts();
            $line->saveQuietly();
        }

        $this->refreshTotal();
    }

    private function sumLineLcy(string $column): ?string
    {
        return app(SalesDocumentMonetaryCalculator::class)
            ->total($this->lines()->pluck($column), DecimalPrecision::CURRENCY_SCALE);
    }
}
