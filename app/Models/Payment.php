<?php

// app/Models/Payment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_number',
        'external_reference',
        'payment_direction',
        'party_type',
        'party_id',
        'party_name',
        'payment_method',
        'bank_account_id',
        'bank_account_number',
        'check_number',
        'check_date',
        'counterparty_bank_name',
        'counterparty_account_number',
        'counterparty_routing_number',
        'payment_amount',
        'applied_amount',
        'unapplied_amount',
        'currency_code',
        'currency_factor',
        'payment_amount_lcy',
        'discount_taken',
        'discount_reason',
        'transaction_fee',
        'transaction_fee_lcy',
        'payment_date',
        'posting_date',
        'value_date',
        'clearing_date',
        'status',
        'reconciled',
        'reconciled_at',
        'reconciled_by',
        'bank_statement_line_id',
        'general_business_posting_group_id',
        'posting_group_id',
        'created_by',
        'posted_by',
        'posted_at',
        'voided_at',
        'voided_by',
        'void_reason',
        'internal_notes',
        'memo',
        'dimensions',
    ];

    protected $casts = [
        'payment_amount' => 'decimal:4',
        'applied_amount' => 'decimal:4',
        'unapplied_amount' => 'decimal:4',
        'currency_factor' => 'decimal:6',
        'payment_amount_lcy' => 'decimal:4',
        'discount_taken' => 'decimal:4',
        'transaction_fee' => 'decimal:4',
        'transaction_fee_lcy' => 'decimal:4',
        'payment_date' => 'date',
        'posting_date' => 'date',
        'value_date' => 'date',
        'clearing_date' => 'date',
        'reconciled' => 'boolean',
        'reconciled_at' => 'datetime',
        'posted_at' => 'datetime',
        'voided_at' => 'datetime',
        'dimensions' => 'array',
    ];

    // ==================== RELATIONSHIPS ====================

    public function party(): MorphTo
    {
        return $this->morphTo('party', 'party_type', 'party_id');
    }

    public function customer(): ?BelongsTo
    {
        return $this->party_type === 'CUSTOMER'
            ? $this->belongsTo(Customer::class, 'party_id')
            : null;
    }

    public function vendor(): ?BelongsTo
    {
        return $this->party_type === 'VENDOR'
            ? $this->belongsTo(Vendor::class, 'party_id')
            : null;
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(PaymentApplication::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function generalBusinessPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralBusinessPostingGroup::class);
    }

    // Ledger entries created by this payment
    public function ledgerEntries(): HasMany
    {
        $model = $this->payment_direction === 'RECEIPT'
            ? CustomerLedgerEntry::class
            : VendorLedgerEntry::class;

        return $this->hasMany($model, 'document_number', 'payment_number');
    }

    public function glEntries(): HasMany
    {
        return $this->hasMany(GlEntry::class, 'source_number', 'payment_number')
            ->where('document_type', 'PAYMENT');
    }

    // ==================== SCOPES ====================

    public function scopeReceipts($query)
    {
        return $query->where('payment_direction', 'RECEIPT');
    }

    public function scopeDisbursements($query)
    {
        return $query->where('payment_direction', 'DISBURSEMENT');
    }

    public function scopePosted($query)
    {
        return $query->where('status', 'POSTED');
    }

    public function scopeUnreconciled($query)
    {
        return $query->where('reconciled', false);
    }

    public function scopeForParty($query, string $type, int $id)
    {
        return $query->where('party_type', $type)->where('party_id', $id);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
    }

    // ==================== CALCULATED ATTRIBUTES ====================

    public function getIsFullyAppliedAttribute(): bool
    {
        return $this->unapplied_amount <= 0.01;
    }

    public function getIsPartiallyAppliedAttribute(): bool
    {
        return $this->applied_amount > 0 && ! $this->is_fully_applied;
    }

    public function getIsOnAccountAttribute(): bool
    {
        return $this->applied_amount == 0;
    }

    public function getNetAmountAttribute(): float
    {
        return $this->payment_amount - $this->transaction_fee;
    }

    // ==================== BUSINESS METHODS ====================

    /**
     * Post the payment (creates ledger entries)
     */
    public function post(int $userId): void
    {
        if ($this->status !== 'PENDING') {
            throw new \Exception('Payment is not pending');
        }

        \DB::transaction(function () use ($userId) {
            // 1. Create Payment Applications (if any pre-defined)
            foreach ($this->applications as $application) {
                $this->applyToDocument($application);
            }

            // 2. Create Ledger Entry
            if ($this->payment_direction === 'RECEIPT') {
                $this->postCustomerReceipt();
            } else {
                $this->postVendorPayment();
            }

            // 3. Create G/L Entries
            $this->postGlEntries();

            // 4. Update status
            $this->update([
                'status' => 'POSTED',
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);
        });
    }

    /**
     * Apply payment to specific document (invoice or credit memo)
     */
    public function applyToDocument(array $applicationData, ?int $userId = null): PaymentApplication
    {
        $userId = $userId ?? auth()->id();

        $document = $this->findDocument(
            $applicationData['document_type'],
            $applicationData['document_id']
        );

        if (! $document) {
            throw new \Exception('Document not found');
        }

        // Validate document belongs to same party
        $documentPartyId = $document->customer_id ?? $document->vendor_id;
        if ($documentPartyId !== $this->party_id) {
            throw new \Exception('Document does not belong to this party');
        }

        $amountToApply = min(
            $applicationData['amount'] ?? $document->remaining_amount,
            $this->unapplied_amount,
            $document->remaining_amount
        );

        if ($amountToApply <= 0) {
            throw new \Exception('No amount to apply');
        }

        // Calculate discount
        $discount = $applicationData['discount'] ?? $this->calculateDiscount($document);

        // Create application record
        $application = PaymentApplication::create([
            'payment_id' => $this->id,
            'document_type' => $applicationData['document_type'],
            'document_id' => $document->id,
            'document_number' => $document->document_number,
            'document_original_amount' => $document->grand_total,
            'document_remaining_before' => $document->remaining_amount,
            'amount_applied' => $amountToApply,
            'discount_applied' => $discount,
            'write_off_amount' => $applicationData['write_off'] ?? 0,
            'document_remaining_after' => $document->remaining_amount - $amountToApply - $discount,
            'full_payment' => ($amountToApply + $discount) >= ($document->remaining_amount - 0.01),
            'applied_by' => $userId,
            'applied_at' => now(),
        ]);

        // Update document
        $document->applyPayment($amountToApply + $discount + $application->write_off_amount);

        // Update payment totals
        $this->applied_amount += $amountToApply;
        $this->unapplied_amount = $this->payment_amount - $this->applied_amount;
        $this->discount_taken += $discount;
        $this->save();

        return $application;
    }

    /**
     * Auto-apply payment to oldest open documents
     */
    public function autoApply(string $strategy = 'FIFO'): void
    {
        $openDocuments = $this->getOpenDocuments()
            ->sortBy('due_date'); // FIFO by due date

        foreach ($openDocuments as $doc) {
            if ($this->unapplied_amount <= 0) {
                break;
            }

            $amount = min($this->unapplied_amount, $doc->remaining_amount);

            $this->applyToDocument([
                'document_type' => $this->getDocumentType($doc),
                'document_id' => $doc->id,
                'amount' => $amount,
            ]);
        }
    }

    /**
     * Unapply a specific application (reversal)
     */
    public function unapplyApplication(PaymentApplication $application, int $userId): void
    {
        if ($application->payment_id !== $this->id) {
            throw new \Exception('Application does not belong to this payment');
        }

        \DB::transaction(function () use ($application, $userId) {
            // Reverse document application
            $document = $this->findDocument($application->document_type, $application->document_id);
            if ($document) {
                $document->reversePayment($application->amount_applied + $application->discount_applied);
            }

            // Mark application reversed
            $application->update([
                'reversed' => true,
                'reversed_at' => now(),
                'reversed_by' => $userId,
            ]);

            // Update payment totals
            $this->applied_amount -= $application->amount_applied;
            $this->unapplied_amount += $application->amount_applied;
            $this->discount_taken -= $application->discount_applied;
            $this->save();
        });
    }

    /**
     * Void the payment (if not reconciled)
     */
    public function void(string $reason, int $userId): void
    {
        if ($this->reconciled) {
            throw new \Exception('Cannot void reconciled payment');
        }

        \DB::transaction(function () use ($reason, $userId) {
            // Reverse all applications
            foreach ($this->applications()->where('reversed', false)->get() as $app) {
                $this->unapplyApplication($app, $userId);
            }

            // Reverse ledger entries
            foreach ($this->ledgerEntries as $entry) {
                $entry->reverse($userId, "Void payment {$this->payment_number}");
            }

            // Reverse G/L entries
            // (Implementation depends on your G/L service)

            $this->update([
                'status' => 'VOIDED',
                'voided_at' => now(),
                'voided_by' => $userId,
                'void_reason' => $reason,
            ]);
        });
    }

    // ==================== POSTING METHODS ====================

    protected function postCustomerReceipt(): void
    {
        // Create Customer Ledger Entry (Credit - reduces AR)
        CustomerLedgerEntry::create([
            'entry_number' => CustomerLedgerEntry::getNextEntryNumber($this->party_id),
            'customer_id' => $this->party_id,
            'document_type' => 'PAYMENT',
            'document_number' => $this->payment_number,
            'external_document_number' => $this->external_reference,
            'description' => "Payment {$this->payment_number} - {$this->payment_method}",
            'posting_date' => $this->posting_date,
            'document_date' => $this->payment_date,
            'debit_amount' => 0,
            'credit_amount' => $this->payment_amount,
            'amount' => -$this->payment_amount,
            'running_balance' => CustomerLedgerEntry::calculateNewBalance(
                $this->party_id,
                -$this->payment_amount
            ),
            'remaining_amount' => $this->unapplied_amount, // May be unapplied
            'open' => $this->unapplied_amount > 0.01,
            'currency_code' => $this->currency_code,
            'original_credit_amount' => $this->payment_amount / $this->currency_factor,
            'currency_factor' => $this->currency_factor,
            'general_business_posting_group_id' => $this->general_business_posting_group_id,
            'created_by' => $this->created_by,
        ]);
    }

    protected function postVendorPayment(): void
    {
        // Create Vendor Ledger Entry (Debit - reduces AP)
        VendorLedgerEntry::create([
            'entry_number' => VendorLedgerEntry::getNextEntryNumber($this->party_id),
            'vendor_id' => $this->party_id,
            'document_type' => 'PAYMENT',
            'document_number' => $this->payment_number,
            'external_document_number' => $this->external_reference,
            'description' => "Payment {$this->payment_number} - {$this->payment_method}",
            'posting_date' => $this->posting_date,
            'document_date' => $this->payment_date,
            'debit_amount' => $this->payment_amount,
            'credit_amount' => 0,
            'amount' => $this->payment_amount, // Positive for vendor (debit)
            'running_balance' => VendorLedgerEntry::calculateNewBalance(
                $this->party_id,
                $this->payment_amount
            ),
            'remaining_amount' => 0, // Payments are always closed
            'open' => false,
            'currency_code' => $this->currency_code,
            'original_debit_amount' => $this->payment_amount / $this->currency_factor,
            'currency_factor' => $this->currency_factor,
            'general_business_posting_group_id' => $this->general_business_posting_group_id,
            'created_by' => $this->created_by,
        ]);
    }

    protected function postGlEntries(): void
    {
        $postingService = new PostingService;

        if ($this->payment_direction === 'RECEIPT') {
            // CUSTOMER PAYMENT
            // Debit: Bank (Cash increases)
            // Credit: A/R (from Customer Posting Group)

            $postingService->postPaymentReceipt(
                customer: $this->customer,
                amount: $this->payment_amount,
                bankAccount: $this->bankAccount,
                discount: $this->discount_taken,
                postingDate: $this->posting_date,
                documentNumber: $this->payment_number
            );
        } else {
            // VENDOR PAYMENT
            // Debit: A/P (from Vendor Posting Group)
            // Credit: Bank (Cash decreases)
            // Debit: Discount Received (if early payment discount)

            $postingService->postPaymentDisbursement(
                vendor: $this->vendor,
                amount: $this->payment_amount,
                bankAccount: $this->bankAccount,
                discount: $this->discount_taken,
                postingDate: $this->posting_date,
                documentNumber: $this->payment_number
            );
        }
    }

    // ==================== HELPER METHODS ====================

    protected function findDocument(string $type, int $id): ?Model
    {
        return match ($type) {
            'SALES_INVOICE' => PostedSalesInvoice::find($id),
            'SALES_CREDIT_MEMO' => PostedSalesCreditMemo::find($id),
            'PURCHASE_INVOICE' => PurchaseInvoice::find($id),
            'PURCHASE_CREDIT_MEMO' => PostedPurchaseCreditMemo::find($id),
            default => null,
        };
    }

    protected function getDocumentType(Model $document): string
    {
        return match ($document::class) {
            PostedSalesInvoice::class => 'SALES_INVOICE',
            PostedSalesCreditMemo::class => 'SALES_CREDIT_MEMO',
            PurchaseInvoice::class => 'PURCHASE_INVOICE',
            PostedPurchaseCreditMemo::class => 'PURCHASE_CREDIT_MEMO',
            default => 'UNKNOWN',
        };
    }

    protected function getOpenDocuments()
    {
        if ($this->party_type === 'CUSTOMER') {
            return PostedSalesInvoice::forCustomer($this->party_id)
                ->where('paid_in_full', false)
                ->get();
        } else {
            return PurchaseInvoice::forVendor($this->party_id)
                ->where('paid_in_full', false)
                ->get();
        }
    }

    protected function calculateDiscount($document): float
    {
        // Early payment discount logic
        // Example: 2% 10 Net 30 (2% discount if paid in 10 days, net due in 30)

        if (! $document->payment_terms_code) {
            return 0;
        }

        // Parse terms (simplified - you'd have a PaymentTerms model)
        if (str_contains($document->payment_terms_code, '2%10')) {
            $discountDate = $document->posting_date->copy()->addDays(10);
            if ($this->payment_date <= $discountDate) {
                return $document->remaining_amount * 0.02;
            }
        }

        return 0;
    }

    /**
     * Generate payment number
     */
    public static function generateNumber(string $direction): string
    {
        $prefix = $direction === 'RECEIPT' ? 'REC' : 'DIS';
        $year = date('Y');
        $count = self::where('payment_direction', $direction)
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('%s-%d-%06d', $prefix, $year, $count);
    }
}
