<?php

// app/Models/VendorLedgerEntry.php

namespace App\Models;

use App\Services\NumberSeriesService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class VendorLedgerEntry extends Model
{
    use HasFactory;

    protected $table = 'vendor_ledger_entries';

    protected $fillable = [
        'entry_number',
        'vendor_id',
        'document_type',
        'document_number',
        'external_document_number',
        'description',
        'comment',
        'posting_date',
        'document_date',
        'due_date',
        'debit_amount',
        'credit_amount',
        'amount',
        'running_balance',
        'remaining_amount',
        'open',
        'applied_to_entries',
        'fully_applied',
        'currency_id',
        'currency_code',
        'original_debit_amount',
        'original_credit_amount',
        'currency_factor',
        'general_business_posting_group_id',
        'vendor_posting_group_id',
        'gl_entry_id',
        'source_id',
        'source_type',
        'created_by',
        'reversed',
        'reversed_at',
        'reversed_by',
        'reversal_entry_number',
        'payment_terms_code',
        'payment_discount_percent',
        'payment_discount_due_date',
        'retainage_amount',
        'retainage_due_date',
        'dimensions',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'document_date' => 'date',
        'due_date' => 'date',
        'debit_amount' => 'decimal:4',
        'credit_amount' => 'decimal:4',
        'amount' => 'decimal:4',
        'running_balance' => 'decimal:4',
        'remaining_amount' => 'decimal:4',
        'open' => 'boolean',
        'applied_to_entries' => 'array',
        'fully_applied' => 'boolean',
        'original_debit_amount' => 'decimal:4',
        'original_credit_amount' => 'decimal:4',
        'currency_factor' => 'decimal:6',
        'reversed' => 'boolean',
        'reversed_at' => 'datetime',
        'payment_discount_percent' => 'decimal:2',
        'payment_discount_due_date' => 'date',
        'retainage_amount' => 'decimal:4',
        'retainage_due_date' => 'date',
        'dimensions' => 'array',
        'currency_id' => 'integer',
    ];

    // ==================== RELATIONSHIPS ====================

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function generalBusinessPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralBusinessPostingGroup::class);
    }

    public function vendorPostingGroup(): BelongsTo
    {
        return $this->belongsTo(VendorPostingGroup::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function glEntry(): BelongsTo
    {
        return $this->belongsTo(GlEntry::class, 'gl_entry_id');
    }

    // Polymorphic source
    public function source(): MorphTo
    {
        return $this->morphTo('source', 'source_type', 'source_id');
    }

    // ==================== SCOPES ====================

    public function scopeForVendor($query, int $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function scopeOpen($query)
    {
        return $query->where('open', true)
            ->where('remaining_amount', '!=', 0);
    }

    public function scopeOverdue($query, ?int $days = null)
    {
        $query = $query->where('open', true)
            ->where('due_date', '<', now());

        if ($days) {
            $query->where('due_date', '<', now()->subDays($days));
        }

        return $query;
    }

    public function scopeDiscountEligible($query)
    {
        return $query->where('open', true)
            ->whereNotNull('payment_discount_due_date')
            ->where('payment_discount_due_date', '>=', now());
    }

    public function scopeNotReversed($query)
    {
        return $query->where('reversed', false);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('posting_date', [$startDate, $endDate]);
    }

    public function scopeByDocumentType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    public function scopeInvoices($query)
    {
        return $query->where('document_type', 'PURCHASE_INVOICE');
    }

    public function scopePayments($query)
    {
        return $query->where('document_type', 'PAYMENT');
    }

    public function scopeCreditMemos($query)
    {
        return $query->where('document_type', 'PURCHASE_CREDIT_MEMO');
    }

    // ==================== CALCULATED ATTRIBUTES ====================

    public function getIsDebitEntryAttribute(): bool
    {
        return $this->debit_amount > 0;
    }

    public function getIsCreditEntryAttribute(): bool
    {
        return $this->credit_amount > 0;
    }

    public function getIsInvoiceAttribute(): bool
    {
        return $this->document_type === 'PURCHASE_INVOICE';
    }

    public function getIsPaymentAttribute(): bool
    {
        return in_array($this->document_type, ['PAYMENT', 'BANK_TRANSFER']);
    }

    public function getIsCreditMemoAttribute(): bool
    {
        return $this->document_type === 'PURCHASE_CREDIT_MEMO';
    }

    public function getDaysOverdueAttribute(): ?int
    {
        if (! $this->open || ! $this->due_date || $this->due_date >= now()) {
            return null;
        }

        return $this->due_date->diffInDays(now());
    }

    public function getAgingCategoryAttribute(): string
    {
        if (! $this->days_overdue) {
            return 'CURRENT';
        }

        return match (true) {
            $this->days_overdue <= 30 => '1-30',
            $this->days_overdue <= 60 => '31-60',
            $this->days_overdue <= 90 => '61-90',
            default => 'OVER_90',
        };
    }

    public function getDiscountAvailableAttribute(): ?float
    {
        if (! $this->is_invoice || ! $this->open || ! $this->payment_discount_due_date) {
            return null;
        }

        if (now() > $this->payment_discount_due_date) {
            return null; // Discount expired
        }

        return $this->remaining_amount * ($this->payment_discount_percent / 100);
    }

    public function getDaysUntilDiscountExpiresAttribute(): ?int
    {
        if (! $this->payment_discount_due_date) {
            return null;
        }

        if (now() > $this->payment_discount_due_date) {
            return null;
        }

        return now()->diffInDays($this->payment_discount_due_date);
    }

    // ==================== BUSINESS METHODS ====================

    /**
     * Apply this payment/credit memo to open invoice entries
     */
    public function applyToEntries(array $applications): void
    {
        // $applications = [['entry_id' => 123, 'amount' => 500.00], ...]

        if (! $this->is_credit_entry) {
            throw new \Exception('Only credit entries can be applied');
        }

        $totalApplied = 0;
        $appliedEntries = $this->applied_to_entries ?? [];

        foreach ($applications as $app) {
            $invoiceEntry = self::find($app['entry_id']);

            if (! $invoiceEntry || ! $invoiceEntry->is_invoice || ! $invoiceEntry->open) {
                continue;
            }

            $applyAmount = min(
                $app['amount'],
                $this->remaining_amount - $totalApplied,
                $invoiceEntry->remaining_amount
            );

            if ($applyAmount <= 0) {
                continue;
            }

            // Update invoice entry
            $invoiceEntry->remaining_amount -= $applyAmount;
            $invoiceEntry->open = $invoiceEntry->remaining_amount > 0.01;
            $invoiceEntry->save();

            // Track application
            $appliedEntries[] = [
                'entry_id' => $invoiceEntry->id,
                'document_number' => $invoiceEntry->document_number,
                'amount' => $applyAmount,
                'applied_at' => now()->toDateTimeString(),
            ];

            $totalApplied += $applyAmount;
        }

        // Update this entry
        $this->remaining_amount -= $totalApplied;
        $this->applied_to_entries = $appliedEntries;
        $this->fully_applied = $this->remaining_amount <= 0.01;
        $this->open = ! $this->fully_applied;

        if ($this->fully_applied) {
            $this->remaining_amount = 0;
        }

        $this->save();
    }

    /**
     * Apply this credit memo to a specific invoice
     */
    public function applyToInvoice(PurchaseInvoice $invoice, ?float $amount = null): void
    {
        if (! $this->is_credit_memo && ! $this->is_payment) {
            throw new \Exception('Entry must be a credit memo or payment');
        }

        // Find the invoice's ledger entry
        $invoiceEntry = self::where('document_type', 'PURCHASE_INVOICE')
            ->where('document_number', $invoice->document_number)
            ->where('vendor_id', $this->vendor_id)
            ->first();

        if (! $invoiceEntry) {
            throw new \Exception('Invoice ledger entry not found');
        }

        $applyAmount = $amount ?? min($this->remaining_amount, $invoice->remaining_amount);

        $this->applyToEntries([[
            'entry_id' => $invoiceEntry->id,
            'amount' => $applyAmount,
        ]]);

        // Update invoice paid status
        if ($applyAmount >= $invoice->remaining_amount - 0.01) {
            $invoice->update([
                'paid_in_full' => true,
                'paid_in_full_date' => now(),
                'remaining_amount' => 0,
            ]);
        } else {
            $invoice->decrement('remaining_amount', $applyAmount);
            $invoice->increment('amount_paid', $applyAmount);
        }
    }

    /**
     * Reverse this entry (creates correcting entry)
     */
    public function reverse(int $userId, string $reason): self
    {
        if ($this->reversed) {
            throw new \Exception('Entry is already reversed');
        }

        return \DB::transaction(function () use ($userId, $reason) {
            // Create reversal entry (opposite amounts)
            $reversal = self::create([
                'entry_number' => $this->getNextEntryNumber($this->vendor_id),
                'vendor_id' => $this->vendor_id,
                'document_type' => 'ADJUSTMENT',
                'document_number' => 'REV-'.$this->document_number,
                'description' => "Reversal of {$this->document_number}: {$reason}",
                'posting_date' => now(),
                'document_date' => now(),
                'debit_amount' => $this->credit_amount, // Swap
                'credit_amount' => $this->debit_amount,   // Swap
                'amount' => -$this->amount,
                'running_balance' => $this->calculateNewBalance($this->vendor_id, -$this->amount),
                'remaining_amount' => 0, // Reversal is closed immediately
                'open' => false,
                'currency_code' => $this->currency_code,
                'original_debit_amount' => $this->original_credit_amount,
                'original_credit_amount' => $this->original_debit_amount,
                'currency_factor' => $this->currency_factor,
                'general_business_posting_group_id' => $this->general_business_posting_group_id,
                'vendor_posting_group_id' => $this->vendor_posting_group_id,
                'created_by' => $userId,
            ]);

            // Mark original as reversed
            $this->update([
                'reversed' => true,
                'reversed_at' => now(),
                'reversed_by' => $userId,
                'reversal_entry_number' => $reversal->entry_number,
                'remaining_amount' => 0,
                'open' => false,
            ]);

            // If original was applied, unapply first
            if ($this->applied_to_entries) {
                $this->unapplyAll();
            }

            // Create corresponding G/L reversal
            // (Implementation depends on your G/L service)

            return $reversal;
        });
    }

    /**
     * Unapply all applications (for reversal)
     */
    protected function unapplyAll(): void
    {
        foreach ($this->applied_to_entries ?? [] as $app) {
            $invoiceEntry = self::find($app['entry_id']);
            if ($invoiceEntry) {
                $invoiceEntry->remaining_amount += $app['amount'];
                $invoiceEntry->open = true;
                $invoiceEntry->save();
            }
        }

        $this->applied_to_entries = [];
        $this->fully_applied = false;
    }

    /**
     * Calculate running balance for new entry
     */
    protected static function calculateNewBalance(int $vendorId, float $amount): float
    {
        $lastEntry = self::forVendor($vendorId)
            ->orderBy('entry_number', 'desc')
            ->first();

        return ($lastEntry?->running_balance ?? 0) + $amount;
    }

    /**
     * Get next entry number for vendor
     */
    protected static function getNextEntryNumber(int $vendorId): int
    {
        return (self::forVendor($vendorId)->max('entry_number') ?? 0) + 1;
    }

    // ==================== STATIC FACTORY METHODS ====================

    /**
     * Create from PurchaseInvoice
     */
    public static function createFromInvoice(PurchaseInvoice|PostedPurchaseInvoice $invoice): self
    {
        $amount = $invoice->grand_total; // Debit (we owe vendor)

        // Parse payment terms for discount
        $discountPercent = null;
        $discountDueDate = null;

        if ($invoice->payment_terms_code) {
            // Example: "2%10NET30" = 2% discount if paid in 10 days, net 30
            if (preg_match('/(\d+)%(\d+)/', $invoice->payment_terms_code, $matches)) {
                $discountPercent = $matches[1];
                $discountDays = $matches[2];
                $discountDueDate = $invoice->posting_date->copy()->addDays($discountDays);
            }
        }

        return self::create([
            'entry_number' => self::getNextEntryNumber($invoice->vendor_id),
            'vendor_id' => $invoice->vendor_id,
            'document_type' => 'PURCHASE_INVOICE',
            'document_number' => $invoice->document_number,
            'external_document_number' => $invoice->external_document_number,
            'description' => "Invoice {$invoice->document_number}",
            'posting_date' => $invoice->posting_date,
            'document_date' => $invoice->document_date,
            'due_date' => $invoice->due_date,
            'debit_amount' => $amount,
            'credit_amount' => 0,
            'amount' => $amount,
            'running_balance' => self::calculateNewBalance($invoice->vendor_id, $amount),
            'remaining_amount' => $amount,
            'open' => true,
            'currency_code' => $invoice->currency_code,
            'original_debit_amount' => $amount / $invoice->currency_factor,
            'original_credit_amount' => 0,
            'currency_factor' => $invoice->currency_factor,
            'general_business_posting_group_id' => $invoice->general_business_posting_group_id,
            'vendor_posting_group_id' => $invoice->vendor_posting_group_id,
            'gl_entry_id' => $invoice->glEntries()->first()?->id,
            'source_id' => $invoice->id,
            'source_type' => $invoice::class,
            'payment_terms_code' => $invoice->payment_terms_code,
            'payment_discount_percent' => $discountPercent,
            'payment_discount_due_date' => $discountDueDate,
            'created_by' => $invoice->posted_by,
        ]);
    }

    /**
     * Create from PostedPurchaseCreditMemo
     */
    public static function createFromCreditMemo(PostedPurchaseCreditMemo $creditMemo): self
    {
        $amount = -abs((float) $creditMemo->grand_total); // Negative (reduces vendor payable)

        return self::create([
            'entry_number' => self::getNextEntryNumber($creditMemo->vendor_id),
            'vendor_id' => $creditMemo->vendor_id,
            'document_type' => 'PURCHASE_CREDIT_MEMO',
            'document_number' => $creditMemo->document_number,
            'external_document_number' => $creditMemo->external_document_number,
            'description' => "Credit Memo {$creditMemo->document_number}",
            'posting_date' => $creditMemo->posting_date,
            'document_date' => $creditMemo->document_date,
            'due_date' => null, // Credit memos don't have due dates
            'debit_amount' => 0,
            'credit_amount' => abs($amount),
            'amount' => $amount, // Negative
            'running_balance' => self::calculateNewBalance($creditMemo->vendor_id, $amount),
            'remaining_amount' => abs($amount),
            'open' => true,
            'currency_code' => $creditMemo->currency_code,
            'original_debit_amount' => 0,
            'original_credit_amount' => abs($amount) / $creditMemo->currency_factor,
            'currency_factor' => $creditMemo->currency_factor,
            'general_business_posting_group_id' => $creditMemo->general_business_posting_group_id,
            'vendor_posting_group_id' => $creditMemo->vendor_posting_group_id,
            'gl_entry_id' => GlEntry::query()
                ->where('document_type', 'PURCHASE_CREDIT_MEMO')
                ->where('document_number', $creditMemo->document_number)
                ->orderBy('id')
                ->value('id'),
            'source_id' => $creditMemo->id,
            'source_type' => PostedPurchaseCreditMemo::class,
            'created_by' => $creditMemo->posted_by,
        ]);
    }

    /**
     * Create from Payment (disbursement)
     */
    public static function createFromPayment(Payment $payment): self
    {
        $amount = -$payment->payment_amount; // Negative (reduces AP)

        return self::create([
            'entry_number' => self::getNextEntryNumber($payment->party_id),
            'vendor_id' => $payment->party_id,
            'document_type' => 'PAYMENT',
            'document_number' => $payment->payment_number,
            'external_document_number' => $payment->external_reference,
            'description' => "Payment {$payment->payment_number} - {$payment->payment_method}",
            'posting_date' => $payment->posting_date,
            'document_date' => $payment->payment_date,
            'due_date' => null, // Payments don't have due dates
            'debit_amount' => 0,
            'credit_amount' => $payment->payment_amount,
            'amount' => $amount,
            'running_balance' => self::calculateNewBalance($payment->party_id, $amount),
            'remaining_amount' => 0, // Payments are always closed
            'open' => false,
            'currency_code' => $payment->currency_code,
            'original_debit_amount' => 0,
            'original_credit_amount' => $payment->payment_amount / $payment->currency_factor,
            'currency_factor' => $payment->currency_factor,
            'general_business_posting_group_id' => $payment->general_business_posting_group_id,
            'vendor_posting_group_id' => $payment->posting_group_id,
            'gl_entry_id' => $payment->glEntries()->first()?->id,
            'source_id' => $payment->id,
            'source_type' => Payment::class,
            'created_by' => $payment->created_by,
        ]);
    }

    /**
     * Create payment entry (legacy method - prefer createFromPayment)
     */
    public static function createPayment(
        int $vendorId,
        float $amount,
        string $paymentMethod,
        string $reference,
        \DateTime $postingDate,
        int $userId,
        ?array $applications = null
    ): self {
        $entry = self::create([
            'entry_number' => self::getNextEntryNumber($vendorId),
            'vendor_id' => $vendorId,
            'document_type' => match ($paymentMethod) {
                'BANK_TRANSFER' => 'BANK_TRANSFER',
                default => 'PAYMENT',
            },
            'document_number' => self::generatePaymentNumber(),
            'external_document_number' => $reference,
            'description' => "Payment - {$paymentMethod}",
            'posting_date' => $postingDate,
            'document_date' => $postingDate,
            'debit_amount' => 0,
            'credit_amount' => $amount,
            'amount' => -$amount, // Negative (reduces balance)
            'running_balance' => self::calculateNewBalance($vendorId, -$amount),
            'remaining_amount' => 0, // Payments are closed immediately
            'open' => false,
            'created_by' => $userId,
        ]);

        if ($applications) {
            $entry->applyToEntries($applications);
        }

        return $entry;
    }

    /**
     * Generate payment document number
     */
    protected static function generatePaymentNumber(): string
    {
        return app(NumberSeriesService::class)->getNextNoFromSeries(['PAYMENT'], null, 'Vendor Payment');
    }

    // ==================== REPORTING METHODS ====================

    /**
     * Get vendor balance as of date
     */
    public static function getBalance(int $vendorId, ?\DateTime $asOf = null): float
    {
        $query = self::forVendor($vendorId)->notReversed();

        if ($asOf) {
            $query->where('posting_date', '<=', $asOf);
        }

        return $query->sum('amount');
    }

    /**
     * Get aging buckets for vendor
     */
    public static function getAging(int $vendorId): array
    {
        $openEntries = self::forVendor($vendorId)
            ->open()
            ->notReversed()
            ->get();

        $aging = [
            'CURRENT' => 0,
            '1-30' => 0,
            '31-60' => 0,
            '61-90' => 0,
            'OVER_90' => 0,
            'TOTAL' => 0,
        ];

        foreach ($openEntries as $entry) {
            if ($entry->is_invoice) {
                $category = $entry->aging_category;
                $aging[$category] += $entry->remaining_amount;
                $aging['TOTAL'] += $entry->remaining_amount;
            }
        }

        return $aging;
    }

    /**
     * Get available discounts for vendor (opportunities to save)
     */
    public static function getAvailableDiscounts(int $vendorId): array
    {
        return self::forVendor($vendorId)
            ->open()
            ->whereNotNull('payment_discount_due_date')
            ->where('payment_discount_due_date', '>=', now())
            ->where('payment_discount_due_date', '<=', now()->addDays(7)) // Due within week
            ->get()
            ->map(fn ($entry) => [
                'entry' => $entry,
                'discount_amount' => $entry->discount_available,
                'expires_in_days' => $entry->days_until_discount_expires,
            ])
            ->filter(fn ($item) => $item['discount_amount'] > 0)
            ->toArray();
    }
}
