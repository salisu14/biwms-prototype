<?php

// app/Models/PostedSalesCreditMemo.php

namespace App\Models;

use App\Exceptions\BusinessException;
use App\Services\NumberSeriesService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PostedSalesCreditMemo extends Model
{
    use HasFactory;

    protected $table = 'posted_sales_credit_memos';

    protected $fillable = [
        'document_number',
        'external_document_number',
        'corrected_invoice_id',
        'corrected_invoice_number',
        'order_id',
        'order_number',
        'customer_id',
        'customer_name',
        'customer_address',
        'ship_to_name',
        'ship_to_address',
        'general_business_posting_group_id',
        'customer_posting_group_id',
        'vat_bus_posting_group',
        'location_id',
        'credit_memo_type',
        'return_reason_code',
        'return_reason_comment',
        'posting_date',
        'document_date',
        'vat_date',
        'subtotal',
        'line_discount_total',
        'total_amount',
        'total_vat',
        'grand_total',
        'currency_code',
        'currency_factor',
        'amount_applied',
        'remaining_amount',
        'fully_applied',
        'fully_applied_date',
        'refunded',
        'refund_amount',
        'refunded_at',
        'refund_reference',
        'posted_by',
        'posted_at',
        'salesperson_id',
        'corrected',
        'corrected_at',
        'correcting_document_number',
        'dimensions',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'document_date' => 'date',
        'vat_date' => 'date',
        'subtotal' => 'decimal:4',
        'line_discount_total' => 'decimal:4',
        'total_amount' => 'decimal:4',
        'total_vat' => 'decimal:4',
        'grand_total' => 'decimal:4',
        'currency_factor' => 'decimal:6',
        'amount_applied' => 'decimal:4',
        'remaining_amount' => 'decimal:4',
        'fully_applied' => 'boolean',
        'fully_applied_date' => 'datetime',
        'refunded' => 'boolean',
        'refund_amount' => 'decimal:4',
        'refunded_at' => 'datetime',
        'posted_at' => 'datetime',
        'corrected' => 'boolean',
        'corrected_at' => 'datetime',
        'dimensions' => 'array',
    ];

    // ==================== RELATIONSHIPS ====================

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function correctedInvoice(): BelongsTo
    {
        return $this->belongsTo(PostedSalesInvoice::class, 'corrected_invoice_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PostedSalesCreditMemoLine::class, 'posted_sales_credit_memo_id')
            ->orderBy('line_number');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function generalBusinessPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralBusinessPostingGroup::class);
    }

    public function customerPostingGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerPostingGroup::class);
    }

    public function glEntries(): HasMany
    {
        return $this->hasMany(GlEntry::class, 'source_number', 'document_number')
            ->where('source_type', 'CUSTOMER')
            ->where('document_type', 'SALES_CREDIT_MEMO');
    }

    // Application entries (where this CM was applied to invoices)
    public function applicationEntries(): HasMany
    {
        return $this->hasMany(CustomerLedgerEntry::class, 'document_number', 'document_number')
            ->where('document_type', 'CREDIT_MEMO_APPLICATION');
    }

    public function ledgerApplications(): HasMany
    {
        return $this->hasMany(CustomerLedgerApplication::class, 'source_posted_sales_credit_memo_id');
    }

    // ==================== SCOPES ====================

    public function scopeNotCorrected($query)
    {
        return $query->where('corrected', false);
    }

    public function scopeOpen($query)
    {
        return $query->where('fully_applied', false)
            ->where('remaining_amount', '!=', 0);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForInvoice($query, int $invoiceId)
    {
        return $query->where('corrected_invoice_id', $invoiceId);
    }

    public function scopeReturns($query)
    {
        return $query->where('credit_memo_type', 'RETURN');
    }

    public function scopeAllowances($query)
    {
        return $query->where('credit_memo_type', 'ALLOWANCE');
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('posting_date', [$startDate, $endDate]);
    }

    // ==================== CALCULATED ATTRIBUTES ====================

    public function getStatusAttribute(): string
    {
        if ($this->corrected) {
            return 'CORRECTED';
        }
        if ($this->fully_applied) {
            return 'FULLY_APPLIED';
        }
        if ($this->refunded) {
            return 'REFUNDED';
        }

        return 'OPEN';
    }

    public function getIsReturnAttribute(): bool
    {
        return $this->credit_memo_type === 'RETURN';
    }

    public function getIsInventoryReturnAttribute(): bool
    {
        return $this->is_return && $this->lines->contains(fn ($line) => $line->is_inventory_item);
    }

    // ==================== BUSINESS METHODS ====================

    /**
     * Apply credit memo to customer invoice(s)
     */
    public function applyToInvoices(array $applications): void
    {
        DB::transaction(function () use ($applications): void {
            /** @var self $creditMemo */
            $creditMemo = self::query()
                ->lockForUpdate()
                ->findOrFail($this->id);

            if ($creditMemo->corrected) {
                throw new BusinessException('Corrected sales credit memos cannot be applied.');
            }

            $creditMemoEntry = CustomerLedgerEntry::query()
                ->where('customer_id', $creditMemo->customer_id)
                ->where('document_type', 'SALES_CREDIT_MEMO')
                ->where('source_type', self::class)
                ->where('source_id', $creditMemo->id)
                ->lockForUpdate()
                ->first();

            if (! $creditMemoEntry) {
                throw new BusinessException('Customer ledger entry not found for credit memo.');
            }

            if ($creditMemoEntry->reversed) {
                throw new BusinessException('Reversed sales credit memo ledger entries cannot be applied.');
            }

            foreach ($applications as $application) {
                $creditMemoRemainingBefore = round((float) $creditMemoEntry->remaining_amount, 4);

                if ($creditMemoRemainingBefore <= 0.0001) {
                    throw new BusinessException('Sales credit memo has no remaining amount to apply.');
                }

                $invoice = $this->lockTargetInvoice($application);
                $invoiceLedgerEntry = $this->lockTargetInvoiceLedgerEntry($invoice);
                $amount = round((float) ($application['amount'] ?? 0), 4);
                $invoiceRemainingBefore = round((float) $invoiceLedgerEntry->remaining_amount, 4);

                $this->validateApplication($creditMemo, $creditMemoEntry, $invoice, $invoiceLedgerEntry, $amount);

                $creditMemoRemainingAfter = round($creditMemoRemainingBefore - $amount, 4);
                $invoiceRemainingAfter = round($invoiceRemainingBefore - $amount, 4);

                if (abs($creditMemoRemainingAfter) <= 0.0001) {
                    $creditMemoRemainingAfter = 0.0;
                }

                if (abs($invoiceRemainingAfter) <= 0.0001) {
                    $invoiceRemainingAfter = 0.0;
                }

                CustomerLedgerApplication::query()->create([
                    'customer_id' => $creditMemo->customer_id,
                    'source_customer_ledger_entry_id' => $creditMemoEntry->id,
                    'target_customer_ledger_entry_id' => $invoiceLedgerEntry->id,
                    'source_posted_sales_credit_memo_id' => $creditMemo->id,
                    'target_posted_sales_invoice_id' => $invoice->id,
                    'amount' => $amount,
                    'currency_code' => $creditMemo->currency_code ?: $invoice->currency_code,
                    'currency_id' => $creditMemo->currency_id ?: $invoice->currency_id,
                    'source_remaining_before' => $creditMemoRemainingBefore,
                    'source_remaining_after' => $creditMemoRemainingAfter,
                    'target_remaining_before' => $invoiceRemainingBefore,
                    'target_remaining_after' => $invoiceRemainingAfter,
                    'applied_at' => now(),
                    'applied_by' => Auth::id(),
                    'idempotency_key' => $this->applicationIdempotencyKey($creditMemo, $invoice, $amount, $creditMemoRemainingBefore, $invoiceRemainingBefore),
                    'metadata' => [
                        'source_document_number' => $creditMemo->document_number,
                        'target_document_number' => $invoice->document_number,
                        'application_type' => 'sales_credit_memo_to_sales_invoice',
                    ],
                ]);

                $creditMemoEntry->forceFill([
                    'remaining_amount' => $creditMemoRemainingAfter,
                    'open' => $creditMemoRemainingAfter > 0.0001,
                    'fully_applied' => $creditMemoRemainingAfter <= 0.0001,
                    'applied_to_entries' => $this->appendLedgerApplicationSnapshot(
                        $creditMemoEntry,
                        $invoiceLedgerEntry,
                        $invoice,
                        $amount,
                    ),
                ])->save();

                $invoiceLedgerEntry->forceFill([
                    'remaining_amount' => $invoiceRemainingAfter,
                    'open' => $invoiceRemainingAfter > 0.0001,
                    'fully_applied' => $invoiceRemainingAfter <= 0.0001,
                ])->save();

                $newAmountPaid = round((float) $invoice->amount_paid + $amount, 4);

                $invoice->forceFill([
                    'amount_paid' => $newAmountPaid,
                    'remaining_amount' => max(0, $invoiceRemainingAfter),
                    'paid_in_full' => $invoiceRemainingAfter <= 0.0001,
                    'paid_in_full_date' => $invoiceRemainingAfter <= 0.0001 ? now() : null,
                ])->save();

                $creditMemo->refresh();
                $creditMemoEntry->refresh();
            }

            $creditMemoEntry->refresh();
            $remainingAmount = max(0, round((float) $creditMemoEntry->remaining_amount, 4));
            $amountApplied = max(0, round(abs((float) $creditMemo->grand_total) - $remainingAmount, 4));

            $creditMemo->forceFill([
                'amount_applied' => $amountApplied,
                'remaining_amount' => $remainingAmount,
                'fully_applied' => $remainingAmount <= 0.0001,
                'fully_applied_date' => $remainingAmount <= 0.0001 ? now() : null,
            ])->save();

            $this->setRawAttributes($creditMemo->getAttributes(), true);
        });
    }

    private function lockTargetInvoice(array $application): PostedSalesInvoice
    {
        $invoiceId = $application['invoice_id'] ?? null;

        if (! $invoiceId && isset($application['entry_id'])) {
            $invoiceId = CustomerLedgerEntry::query()
                ->whereKey($application['entry_id'])
                ->where('document_type', 'SALES_INVOICE')
                ->value('source_id');
        }

        if (! $invoiceId) {
            throw new BusinessException('A target posted sales invoice is required.');
        }

        /** @var PostedSalesInvoice|null $invoice */
        $invoice = PostedSalesInvoice::query()
            ->lockForUpdate()
            ->find($invoiceId);

        if (! $invoice) {
            throw new BusinessException('Target posted sales invoice was not found.');
        }

        return $invoice;
    }

    private function lockTargetInvoiceLedgerEntry(PostedSalesInvoice $invoice): CustomerLedgerEntry
    {
        /** @var CustomerLedgerEntry|null $invoiceLedgerEntry */
        $invoiceLedgerEntry = CustomerLedgerEntry::query()
            ->where('customer_id', $invoice->customer_id)
            ->where('document_type', 'SALES_INVOICE')
            ->where('source_type', PostedSalesInvoice::class)
            ->where('source_id', $invoice->id)
            ->lockForUpdate()
            ->first();

        if (! $invoiceLedgerEntry) {
            throw new BusinessException('Customer ledger entry not found for target posted sales invoice.');
        }

        return $invoiceLedgerEntry;
    }

    private function validateApplication(
        self $creditMemo,
        CustomerLedgerEntry $creditMemoEntry,
        PostedSalesInvoice $invoice,
        CustomerLedgerEntry $invoiceLedgerEntry,
        float $amount,
    ): void {
        if ((int) $invoice->customer_id !== (int) $creditMemo->customer_id) {
            throw new BusinessException('Sales credit memo can only be applied to invoices for the same customer.');
        }

        if ($invoice->cancelled) {
            throw new BusinessException('Cancelled posted sales invoices cannot receive credit memo applications.');
        }

        if ($invoiceLedgerEntry->reversed) {
            throw new BusinessException('Reversed sales invoice ledger entries cannot receive credit memo applications.');
        }

        if ((bool) $invoiceLedgerEntry->open === false || (float) $invoiceLedgerEntry->remaining_amount <= 0.0001) {
            throw new BusinessException('Sales credit memo cannot be applied to a fully paid invoice.');
        }

        if ($amount <= 0.0001) {
            throw new BusinessException('Application amount must be greater than zero.');
        }

        if ($amount - (float) $creditMemoEntry->remaining_amount > 0.0001) {
            throw new BusinessException('Application amount exceeds the remaining credit memo amount.');
        }

        if ($amount - (float) $invoiceLedgerEntry->remaining_amount > 0.0001) {
            throw new BusinessException('Application amount exceeds the remaining invoice amount.');
        }

        if (($creditMemo->currency_code ?: null) !== ($invoice->currency_code ?: null)) {
            throw new BusinessException('Sales credit memo and invoice currencies must match.');
        }

        if ($creditMemo->currency_id && $invoice->currency_id && (int) $creditMemo->currency_id !== (int) $invoice->currency_id) {
            throw new BusinessException('Sales credit memo and invoice currency records must match.');
        }

        $creditMemoBusinessId = $this->businessIdForApplication($creditMemo);
        $invoiceBusinessId = $this->businessIdForApplication($invoice);

        if ($creditMemoBusinessId !== null && $invoiceBusinessId !== null && $creditMemoBusinessId !== $invoiceBusinessId) {
            throw new BusinessException('Sales credit memo and invoice must belong to the same business.');
        }
    }

    private function businessIdForApplication(Model $document): ?int
    {
        $dimensions = $document->dimensions;

        if (! is_array($dimensions)) {
            return null;
        }

        $businessId = $dimensions['business_id'] ?? $dimensions['business'] ?? null;

        return is_numeric($businessId) ? (int) $businessId : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function appendLedgerApplicationSnapshot(
        CustomerLedgerEntry $creditMemoEntry,
        CustomerLedgerEntry $invoiceLedgerEntry,
        PostedSalesInvoice $invoice,
        float $amount,
    ): array {
        return [
            ...($creditMemoEntry->applied_to_entries ?? []),
            [
                'entry_id' => $invoiceLedgerEntry->id,
                'document_number' => $invoice->document_number,
                'amount' => $amount,
                'applied_at' => now()->toDateTimeString(),
                'trace_type' => CustomerLedgerApplication::class,
            ],
        ];
    }

    private function applicationIdempotencyKey(
        self $creditMemo,
        PostedSalesInvoice $invoice,
        float $amount,
        float $creditMemoRemainingBefore,
        float $invoiceRemainingBefore,
    ): string {
        return hash('sha256', implode('|', [
            self::class,
            $creditMemo->id,
            PostedSalesInvoice::class,
            $invoice->id,
            number_format($amount, 4, '.', ''),
            number_format($creditMemoRemainingBefore, 4, '.', ''),
            number_format($invoiceRemainingBefore, 4, '.', ''),
        ]));
    }

    /**
     * Process refund to customer
     */
    public function processRefund(float $amount, string $reference, ?\DateTime $date = null): void
    {
        if ($amount > $this->remaining_amount) {
            throw new \Exception('Refund amount exceeds remaining credit');
        }

        $this->update([
            'refunded' => true,
            'refund_amount' => $amount,
            'refunded_at' => $date ?? now(),
            'refund_reference' => $reference,
        ]);

        // Create G/L entry for cash out
        // Implementation depends on your cash management
    }

    /**
     * Create warehouse receipt for physical return
     */
    public function createWarehouseReceipt(?int $userId = null): ?WarehouseReceipt
    {
        if (! $this->is_inventory_return) {
            return null; // No physical goods to receive
        }

        return DB::transaction(function () use ($userId) {
            $receipt = WarehouseReceipt::create([
                'document_number' => WarehouseReceipt::generateNumber(),
                'location_id' => $this->location_id,
                'source_document' => 'SALES_RETURN',
                'source_document_id' => $this->id,
                'source_document_number' => $this->document_number,
                'customer_id' => $this->customer_id, // Return from customer
                'status' => 'OPEN',
                'assigned_user_id' => $userId,
                'receipt_date' => now(),
                'expected_receipt_date' => now(),
            ]);

            foreach ($this->lines as $cmLine) {
                if (! $cmLine->is_inventory_item) {
                    continue;
                }

                $receipt->lines()->create([
                    'line_number' => $cmLine->line_number,
                    'item_id' => $cmLine->item_id,
                    'variant_code' => $cmLine->variant_code,
                    'description' => $cmLine->item_description,
                    'quantity' => abs($cmLine->quantity), // Positive for receipt
                    'unit_of_measure_code' => $cmLine->unit_of_measure_code,
                    'source_line_id' => $cmLine->id,
                ]);
            }

            return $receipt;
        });
    }

    /**
     * Correct this credit memo (creates a new correcting document)
     */
    public function correct(array $corrections, int $userId): PostedSalesCreditMemo
    {
        if ($this->corrected) {
            throw new \Exception('Credit memo is already corrected');
        }

        return DB::transaction(function () use ($corrections, $userId) {
            // Create reversing credit memo (positive amounts)
            $correctingCm = self::create([
                'document_number' => self::generateNumber(),
                'corrected_invoice_id' => $this->corrected_invoice_id,
                'customer_id' => $this->customer_id,
                'customer_name' => $this->customer_name,
                'credit_memo_type' => 'CORRECTION',
                'posting_date' => now(),
                'document_date' => now(),
                'total_amount' => -$this->total_amount, // Reverse
                'total_vat' => -$this->total_vat,
                'grand_total' => -$this->grand_total,
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);

            // Mark original as corrected
            $this->update([
                'corrected' => true,
                'corrected_at' => now(),
                'correcting_document_number' => $correctingCm->document_number,
            ]);

            // Create new corrected CM with proper amounts
            $newCm = self::createFromCorrections($this, $corrections, $userId);

            return $newCm;
        });
    }

    /**
     * Generate document number
     */
    public static function generateNumber(): string
    {
        return app(NumberSeriesService::class)->getNextNoFromSeries(
            ['S-CM', 'SALES_CREDIT_MEMO', 'SCM'],
            null,
            'Posted Sales Credit Memo'
        );
    }

    /**
     * Create credit memo from sales return order
     */
    public static function createFromReturn(
        SalesOrder $returnOrder, // Return type order
        PostedSalesInvoice $originalInvoice,
        array $returnQuantities, // ['so_line_id' => qty, ...]
        int $userId
    ): self {
        return DB::transaction(function () use ($returnOrder, $originalInvoice, $returnQuantities, $userId) {
            $cm = self::create([
                'document_number' => self::generateNumber(),
                'external_document_number' => $returnOrder->external_document_number,
                'corrected_invoice_id' => $originalInvoice->id,
                'corrected_invoice_number' => $originalInvoice->document_number,
                'order_id' => $returnOrder->id,
                'order_number' => $returnOrder->order_number,
                'customer_id' => $returnOrder->customer_id,
                'customer_name' => $returnOrder->customer_name,
                'customer_address' => $returnOrder->customer_address,
                'general_business_posting_group_id' => $returnOrder->general_business_posting_group_id,
                'customer_posting_group_id' => $returnOrder->customer_posting_group_id,
                'vat_bus_posting_group' => $returnOrder->vat_bus_posting_group,
                'location_id' => $returnOrder->location_id,
                'credit_memo_type' => 'RETURN',
                'posting_date' => now(),
                'document_date' => now(),
                'currency_code' => $returnOrder->currency_code,
                'currency_factor' => $returnOrder->currency_factor,
                'remaining_amount' => 0, // Will be calculated
                'posted_by' => $userId,
                'posted_at' => now(),
                'salesperson_id' => $returnOrder->salesperson_id,
            ]);

            $totalAmount = 0;
            $totalVat = 0;

            foreach ($returnOrder->lines as $soLine) {
                $returnQty = $returnQuantities[$soLine->id] ?? 0;
                if ($returnQty <= 0) {
                    continue;
                }

                // Find original invoice line
                $invLine = $originalInvoice->lines()
                    ->where('so_line_id', $soLine->return_against_line_id)
                    ->first();

                if (! $invLine) {
                    continue;
                }

                $ratio = $returnQty / $invLine->quantity;

                // Calculate amounts (negative)
                $lineTotal = -($invLine->unit_price * $returnQty);
                $lineDiscount = -($invLine->line_discount_amount * $ratio);
                $lineAmount = $lineTotal - $lineDiscount;
                $vatAmount = -($invLine->vat_amount * $ratio);

                // COGS reversal (positive - putting back)
                $costAmount = $invLine->unit_cost * $returnQty;

                $cm->lines()->create([
                    'so_line_id' => $soLine->id,
                    'so_line_number' => $soLine->line_number,
                    'item_id' => $soLine->item_id,
                    'item_code' => $soLine->item_code,
                    'item_description' => $soLine->description,
                    'variant_code' => $soLine->variant_code,
                    'general_product_posting_group_id' => $soLine->general_product_posting_group_id,
                    'inventory_posting_group_id' => $soLine->inventory_posting_group_id,
                    'sales_account_id' => $invLine->sales_account_id,
                    'cogs_account_id' => $invLine->cogs_account_id,
                    'inventory_account_id' => $invLine->inventory_account_id,
                    'quantity' => -$returnQty, // Negative
                    'unit_of_measure_code' => $soLine->unit_of_measure_code,
                    'qty_per_unit_of_measure' => $soLine->qty_per_unit_of_measure,
                    'quantity_base' => -$returnQty * $soLine->qty_per_unit_of_measure,
                    'unit_price' => $invLine->unit_price,
                    'unit_cost' => $invLine->unit_cost,
                    'unit_cost_lcy' => $invLine->unit_cost_lcy,
                    'line_discount_percent' => $invLine->line_discount_percent,
                    'line_discount_amount' => $lineDiscount,
                    'line_total' => $lineTotal,
                    'line_amount' => $lineAmount,
                    'vat_code' => $invLine->vat_code,
                    'vat_percentage' => $invLine->vat_percentage,
                    'vat_amount' => $vatAmount,
                    'amount_including_vat' => $lineAmount + $vatAmount,
                    'cost_amount_reversed' => $costAmount,
                    'inventory_amount_reversed' => $costAmount,
                    'return_type' => 'FULL',
                    'line_number' => $soLine->line_number,
                ]);

                $totalAmount += $lineAmount;
                $totalVat += $vatAmount;
            }

            $cm->update([
                'subtotal' => $totalAmount,
                'total_amount' => $totalAmount,
                'total_vat' => $totalVat,
                'grand_total' => $totalAmount + $totalVat,
                'remaining_amount' => abs($totalAmount + $totalVat),
            ]);

            return $cm;
        });
    }
}
