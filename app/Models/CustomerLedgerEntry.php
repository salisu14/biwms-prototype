<?php

// app/Models/CustomerLedgerEntry.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLedgerEntry extends Model
{
    use HasFactory;

    protected $table = 'customer_ledger_entries';

    protected $fillable = [
        'entry_number',
        'customer_id',
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
        'customer_posting_group_id',
        'gl_entry_id',
        'source_id',
        'source_type',
        'created_by',
        'reversed',
        'reversed_at',
        'reversed_by',
        'reversal_entry_number',
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
        'dimensions' => 'array',
        'currency_id' => 'integer',
    ];

    // ==================== RELATIONSHIPS ====================

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function generalBusinessPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralBusinessPostingGroup::class);
    }

    public function customerPostingGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerPostingGroup::class);
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
    public function source()
    {
        return $this->morphTo('source', 'source_type', 'source_id');
    }

    // ==================== SCOPES ====================

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
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
        return $this->document_type === 'SALES_INVOICE';
    }

    public function getIsPaymentAttribute(): bool
    {
        return in_array($this->document_type, ['PAYMENT', 'CASH_RECEIPT', 'BANK_TRANSFER']);
    }

    public function getIsCreditMemoAttribute(): bool
    {
        return $this->document_type === 'SALES_CREDIT_MEMO';
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
    public function applyToInvoice(PostedSalesInvoice $invoice, ?float $amount = null): void
    {
        if (! $this->is_credit_memo && ! $this->is_payment) {
            throw new \Exception('Entry must be a credit memo or payment');
        }

        // Find the invoice's ledger entry
        $invoiceEntry = self::where('document_type', 'SALES_INVOICE')
            ->where('document_number', $invoice->document_number)
            ->where('customer_id', $this->customer_id)
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
        $newAmountPaid = (float) $invoice->amount_paid + $applyAmount;

        if ($applyAmount >= $invoice->remaining_amount - 0.01) {
            $invoice->update([
                'amount_paid' => $newAmountPaid,
                'paid_in_full' => true,
                'paid_in_full_date' => now(),
                'remaining_amount' => 0,
            ]);
        } else {
            $invoice->update([
                'amount_paid' => $newAmountPaid,
                'remaining_amount' => max(0, (float) $invoice->remaining_amount - $applyAmount),
                'paid_in_full' => false,
                'paid_in_full_date' => null,
            ]);
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
                'entry_number' => $this->getNextEntryNumber($this->customer_id),
                'customer_id' => $this->customer_id,
                'document_type' => 'ADJUSTMENT',
                'document_number' => 'REV-'.$this->document_number,
                'description' => "Reversal of {$this->document_number}: {$reason}",
                'posting_date' => now(),
                'document_date' => now(),
                'debit_amount' => $this->credit_amount, // Swap
                'credit_amount' => $this->debit_amount,   // Swap
                'amount' => -$this->amount,
                'running_balance' => $this->calculateNewBalance($this->customer_id, -$this->amount),
                'remaining_amount' => 0, // Reversal is closed immediately
                'open' => false,
                'currency_code' => $this->currency_code,
                'original_debit_amount' => $this->original_credit_amount,
                'original_credit_amount' => $this->original_debit_amount,
                'currency_factor' => $this->currency_factor,
                'general_business_posting_group_id' => $this->general_business_posting_group_id,
                'customer_posting_group_id' => $this->customer_posting_group_id,
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
    protected static function calculateNewBalance(int $customerId, float $amount): float
    {
        $lastEntry = self::forCustomer($customerId)
            ->orderBy('entry_number', 'desc')
            ->first();

        return ($lastEntry?->running_balance ?? 0) + $amount;
    }

    /**
     * Get next entry number for customer
     */
    protected static function getNextEntryNumber(int $customerId): int
    {
        return (self::forCustomer($customerId)->max('entry_number') ?? 0) + 1;
    }

    // ==================== STATIC FACTORY METHODS ====================

    /**
     * Create from PostedSalesInvoice
     */
    public static function createFromInvoice(PostedSalesInvoice $invoice): self
    {
        $amount = $invoice->grand_total; // Debit (customer owes)

        return self::create([
            'entry_number' => self::getNextEntryNumber($invoice->customer_id),
            'customer_id' => $invoice->customer_id,
            'document_type' => 'SALES_INVOICE',
            'document_number' => $invoice->document_number,
            'external_document_number' => $invoice->external_document_number,
            'description' => "Invoice {$invoice->document_number}",
            'posting_date' => $invoice->posting_date,
            'document_date' => $invoice->document_date,
            'due_date' => $invoice->due_date,
            'debit_amount' => $amount,
            'credit_amount' => 0,
            'amount' => $amount,
            'running_balance' => self::calculateNewBalance($invoice->customer_id, $amount),
            'remaining_amount' => $amount,
            'open' => true,
            'currency_code' => $invoice->currency_code,
            'original_debit_amount' => $amount / $invoice->currency_factor,
            'original_credit_amount' => 0,
            'currency_factor' => $invoice->currency_factor,
            'general_business_posting_group_id' => $invoice->general_business_posting_group_id,
            'customer_posting_group_id' => $invoice->customer_posting_group_id,
            'gl_entry_id' => $invoice->glEntries()->first()?->id,
            'source_id' => $invoice->id,
            'source_type' => PostedSalesInvoice::class,
            'created_by' => $invoice->posted_by,
        ]);
    }

    /**
     * Create from PostedSalesCreditMemo
     */
    public static function createFromCreditMemo(PostedSalesCreditMemo $creditMemo): self
    {
        $amount = $creditMemo->grand_total; // Negative (we owe customer)

        return self::create([
            'entry_number' => self::getNextEntryNumber($creditMemo->customer_id),
            'customer_id' => $creditMemo->customer_id,
            'document_type' => 'SALES_CREDIT_MEMO',
            'document_number' => $creditMemo->document_number,
            'external_document_number' => $creditMemo->external_document_number,
            'description' => "Credit Memo {$creditMemo->document_number}",
            'posting_date' => $creditMemo->posting_date,
            'document_date' => $creditMemo->document_date,
            'due_date' => null, // Credit memos don't have due dates
            'debit_amount' => 0,
            'credit_amount' => abs($amount),
            'amount' => $amount, // Negative
            'running_balance' => self::calculateNewBalance($creditMemo->customer_id, $amount),
            'remaining_amount' => abs($amount),
            'open' => true,
            'currency_code' => $creditMemo->currency_code,
            'original_debit_amount' => 0,
            'original_credit_amount' => abs($amount) / $creditMemo->currency_factor,
            'currency_factor' => $creditMemo->currency_factor,
            'general_business_posting_group_id' => $creditMemo->general_business_posting_group_id,
            'customer_posting_group_id' => $creditMemo->customer_posting_group_id,
            'gl_entry_id' => $creditMemo->glEntries()->first()?->id,
            'source_id' => $creditMemo->id,
            'source_type' => PostedSalesCreditMemo::class,
            'created_by' => $creditMemo->posted_by,
        ]);
    }

    /**
     * Create payment entry
     */
    public static function createPayment(
        int $customerId,
        float $amount,
        string $paymentMethod,
        string $reference,
        \DateTime $postingDate,
        int $userId,
        ?array $applications = null
    ): self {
        $entry = self::create([
            'entry_number' => self::getNextEntryNumber($customerId),
            'customer_id' => $customerId,
            'document_type' => match ($paymentMethod) {
                'CASH' => 'CASH_RECEIPT',
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
            'running_balance' => self::calculateNewBalance($customerId, -$amount),
            'remaining_amount' => $applications ? $amount : 0,
            'open' => $applications ? true : false,
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
        $prefix = 'PAY';
        $year = date('Y');
        $count = self::whereYear('created_at', $year)
            ->whereIn('document_type', ['PAYMENT', 'CASH_RECEIPT', 'BANK_TRANSFER'])
            ->count() + 1;

        return sprintf('%s-%d-%06d', $prefix, $year, $count);
    }

    // ==================== REPORTING METHODS ====================

    /**
     * Get customer balance as of date
     */
    public static function getBalance(int $customerId, ?\DateTime $asOf = null): float
    {
        $query = self::forCustomer($customerId)->notReversed();

        if ($asOf) {
            $query->where('posting_date', '<=', $asOf);
        }

        return $query->sum('amount');
    }

    /**
     * Get aging buckets for customer
     */
    public static function getAging(int $customerId): array
    {
        $openEntries = self::forCustomer($customerId)
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
}
