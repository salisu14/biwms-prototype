<?php

namespace App\Models;

use App\Services\NumberSeriesService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PostedPurchaseCreditMemo extends Model
{
    use HasFactory;

    protected $table = 'posted_purchase_credit_memos';

    protected $fillable = [
        // Document Identification
        'document_number',
        'external_document_number',
        'vendor_invoice_number',

        // Vendor Information
        'vendor_id',
        'vendor_name',
        'vendor_address',
        'vendor_city',
        'vendor_post_code',
        'vendor_country',
        'vendor_tax_registration_number',

        // Posting Information
        'posting_date',
        'document_date',
        'due_date',
        'vendor_posting_group_id',
        'general_business_posting_group_id',

        // Currency Information
        'currency_code',
        'currency_factor',

        // Amounts
        'subtotal',
        'discount_amount',
        'tax_amount',
        'grand_total',

        // Posting Status
        'posted',
        'posted_at',
        'posted_by',

        // Source Reference (links to unposted document)
        'source_document_id',
        'source_document_type',

        // Correction Reference
        'corrects_invoice_number',
        'corrects_invoice_id',

        // Payment Terms
        'payment_terms_code',

        // Dimensions
        'dimensions',

        // Reason Code
        'reason_code',
        'description',

        // Location/Warehouse (for WMS)
        'location_code',
        'warehouse_receipt_number',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'document_date' => 'date',
        'due_date' => 'date',
        'posted_at' => 'datetime',
        'subtotal' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'grand_total' => 'decimal:4',
        'currency_factor' => 'decimal:6',
        'posted' => 'boolean',
        'dimensions' => 'array',
    ];

    // ==================== RELATIONSHIPS ====================

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Vendor Posting Group - maps to Accounts Payable control account
     * Part of WMS Posting Groups Setup (Specific Posting Groups)
     */
    public function vendorPostingGroup(): BelongsTo
    {
        return $this->belongsTo(VendorPostingGroup::class, 'vendor_posting_group_id');
    }

    /**
     * General Business Posting Group - categorizes vendor type
     * Part of WMS Posting Groups Setup (General Posting Groups)
     */
    public function generalBusinessPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralBusinessPostingGroup::class, 'general_business_posting_group_id');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PostedPurchaseCreditMemoLine::class, 'credit_memo_id');
    }

    /**
     * Polymorphic relationship to G/L Entries
     */
    public function glEntries(): MorphMany
    {
        return $this->morphMany(GlEntry::class, 'source', 'source_type', 'source_id');
    }

    /**
     * The invoice this credit memo corrects
     */
    public function correctedInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'corrects_invoice_id');
    }

    public function reasonCode(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'reason_code', 'code');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_code', 'code');
    }

    // ==================== SCOPES ====================

    public function scopeForVendor($query, int $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function scopePosted($query)
    {
        return $query->where('posted', true);
    }

    public function scopeUnposted($query)
    {
        return $query->where('posted', false);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('posting_date', [$startDate, $endDate]);
    }

    public function scopeByPostingGroup($query, int $postingGroupId)
    {
        return $query->where('vendor_posting_group_id', $postingGroupId);
    }

    public function scopeByBusinessGroup($query, int $businessGroupId)
    {
        return $query->where('general_business_posting_group_id', $businessGroupId);
    }

    // ==================== CALCULATED ATTRIBUTES ====================

    public function getRemainingAmountAttribute(): float
    {
        // Get related vendor ledger entry
        $ledgerEntry = VendorLedgerEntry::where('source_id', $this->id)
            ->where('source_type', self::class)
            ->first();

        return $ledgerEntry?->remaining_amount ?? $this->grand_total;
    }

    public function getIsFullyAppliedAttribute(): bool
    {
        return $this->remaining_amount <= 0.01;
    }

    public function getAmountInLocalCurrencyAttribute(): float
    {
        if ($this->currency_code === config('app.base_currency', 'USD')) {
            return $this->grand_total;
        }

        return $this->grand_total / ($this->currency_factor ?: 1);
    }

    // ==================== BUSINESS METHODS ====================

    /**
     * Post the credit memo - creates vendor ledger entry and G/L entries
     */
    public function post(int $userId): void
    {
        if ($this->posted) {
            throw new \Exception('Credit memo is already posted');
        }

        \DB::transaction(function () use ($userId) {
            // Update posting information
            $this->update([
                'posted' => true,
                'posted_at' => now(),
                'posted_by' => $userId,
            ]);

            // Create Vendor Ledger Entry
            $ledgerEntry = VendorLedgerEntry::createFromCreditMemo($this);

            // Create G/L Entries based on posting groups
            $this->createGlEntries($ledgerEntry);
        });
    }

    /**
     * Create G/L Entries based on WMS Posting Groups Setup
     */
    protected function createGlEntries(VendorLedgerEntry $ledgerEntry): void
    {
        $localAmount = $this->amount_in_local_currency;

        // 1. Credit Accounts Payable (from Vendor Posting Group)
        $this->glEntries()->create([
            'posting_date' => $this->posting_date,
            'document_number' => $this->document_number,
            'description' => "Credit Memo {$this->document_number}",
            'account_id' => $this->vendorPostingGroup?->payables_account_id,
            'credit_amount' => $localAmount,
            'debit_amount' => 0,
            'amount' => -$localAmount,
            'currency_code' => $this->currency_code,
            'currency_factor' => $this->currency_factor,
            'source_id' => $this->id,
            'source_type' => self::class,
        ]);

        // 2. Debit appropriate accounts based on lines and posting groups
        foreach ($this->lines as $line) {
            $lineAmount = $line->amount * ($line->currency_factor ?: 1);

            // Determine account from General Posting Setup
            $postingSetup = GeneralPostingSetup::where([
                'general_business_posting_group_id' => $this->general_business_posting_group_id,
                'general_product_posting_group_id' => $line->general_product_posting_group_id,
            ])->first();

            $this->glEntries()->create([
                'posting_date' => $this->posting_date,
                'document_number' => $this->document_number,
                'description' => $line->description,
                'account_id' => $postingSetup?->purchase_credit_memo_account_id
                    ?? $postingSetup?->purchase_account_id,
                'debit_amount' => $lineAmount,
                'credit_amount' => 0,
                'amount' => $lineAmount,
                'currency_code' => $this->currency_code,
                'currency_factor' => $this->currency_factor,
                'source_id' => $this->id,
                'source_type' => self::class,
            ]);
        }

        // 3. Tax/VAT entries if applicable
        if ($this->tax_amount > 0) {
            $this->createTaxGlEntries($localAmount);
        }
    }

    /**
     * Apply this credit memo to open invoices
     */
    public function applyToInvoices(array $applications): void
    {
        if (! $this->posted) {
            throw new \Exception('Credit memo must be posted before applying');
        }

        $ledgerEntry = VendorLedgerEntry::where('source_id', $this->id)
            ->where('source_type', self::class)
            ->first();

        if (! $ledgerEntry) {
            throw new \Exception('Vendor ledger entry not found');
        }

        $ledgerEntry->applyToEntries($applications);
    }

    /**
     * Generate next document number
     */
    public static function generateDocumentNumber(): string
    {
        return app(NumberSeriesService::class)->getNextNoFromSeries(
            ['P-CM', 'PURCHASE_CREDIT_MEMO', 'PCM'],
            null,
            'Posted Purchase Credit Memo'
        );
    }
}
