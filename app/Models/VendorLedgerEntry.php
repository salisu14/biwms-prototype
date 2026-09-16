<?php

// app/Models/VendorLedgerEntry.php

namespace App\Models;

use App\Exceptions\BusinessException;
use App\Services\Finance\GeneralLedgerService;
use App\Services\NumberSeriesService;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use App\Support\LedgerSemantics;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VendorLedgerEntry extends Model
{
    use HasFactory;

    protected $table = 'vendor_ledger_entries';

    protected $fillable = [
        'entry_number',
        'vendor_id',
        'business_id',
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
        'original_remaining_amount',
        'ledger_semantics_version',
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
        'original_remaining_amount' => 'decimal:4',
        'ledger_semantics_version' => 'integer',
        'reversed' => 'boolean',
        'reversed_at' => 'datetime',
        'payment_discount_percent' => 'decimal:2',
        'payment_discount_due_date' => 'date',
        'retainage_amount' => 'decimal:4',
        'retainage_due_date' => 'date',
        'dimensions' => 'array',
        'currency_id' => 'integer',
        'business_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (VendorLedgerEntry $entry): void {
            if (! $entry->exists) {
                return;
            }

            $immutableFields = [
                'entry_number', 'vendor_id', 'business_id', 'document_type', 'document_number',
                'external_document_number', 'description', 'comment', 'posting_date',
                'document_date', 'due_date', 'debit_amount', 'credit_amount', 'amount',
                'running_balance', 'currency_id', 'currency_code', 'original_debit_amount',
                'original_credit_amount', 'currency_factor', 'general_business_posting_group_id',
                'vendor_posting_group_id', 'source_id', 'source_type', 'created_by',
            ];

            if (array_intersect($immutableFields, array_keys($entry->getDirty())) !== []) {
                throw new BusinessException('Posted vendor ledger facts are immutable. Use an approved posting, settlement, or reversal service.');
            }

            // Semantic immutability: a legacy/unclassified row can never be
            // promoted to version 2 by a generic fill/update. Only row creation
            // (approved posting, reversal and opening-balance paths) may stamp
            // the prospective semantics, so historical rows can never be
            // silently reinterpreted as LCY-base.
            if (array_key_exists('ledger_semantics_version', $entry->getDirty())
                && ! LedgerSemantics::isVersionTwo($entry->getOriginal('ledger_semantics_version'))
                && LedgerSemantics::isVersionTwo($entry->ledger_semantics_version)) {
                throw new BusinessException('The vendor ledger semantics version is immutable: a legacy vendor ledger entry cannot be promoted to version 2.');
            }
        });

        static::deleting(function (): void {
            throw new BusinessException('Posted vendor ledger entries cannot be deleted.');
        });
    }

    // ==================== RELATIONSHIPS ====================

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
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
            ->where('reversed', false)
            ->where('remaining_amount', '!=', 0);
    }

    public function scopeOverdue($query, ?int $days = null)
    {
        $query = $query->where('open', true)
            ->where('reversed', false)
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

    public function getSignedRemainingAmountAttribute(): float
    {
        return LedgerSemantics::signedRemaining(
            $this->is_debit_entry,
            $this->is_credit_entry,
            $this->amount,
            (float) $this->remaining_amount,
        );
    }

    /**
     * True when this row explicitly declares the version-2 prospective
     * semantics (base columns are LCY, original columns are document currency).
     */
    public function getIsVersionTwoAttribute(): bool
    {
        return LedgerSemantics::isVersionTwo($this->ledger_semantics_version);
    }

    /**
     * The document-currency (FCY) amount still open.
     *
     * Version-2 rows track it explicitly; legacy rows keep it in the base
     * `remaining_amount` column, whose meaning is document currency for them.
     */
    public function getDocumentRemainingAmountAttribute(): ?float
    {
        if ($this->is_version_two) {
            return $this->original_remaining_amount === null
                ? null
                : (float) $this->original_remaining_amount;
        }

        return (float) $this->remaining_amount;
    }

    /**
     * LCY carrying value of the open remaining amount, or null when the row
     * cannot be converted under a trusted rule.
     */
    public function getLcyRemainingAmountAttribute(): ?float
    {
        $lcy = LedgerSemantics::toLcy(
            $this->remaining_amount,
            $this->ledger_semantics_version,
            $this->document_type,
            $this->currency_factor,
            $this->currency_code,
        );

        return $lcy === null ? null : (float) $lcy;
    }

    /**
     * LCY carrying value of the signed `amount` column, or null when the row
     * cannot be converted under a trusted rule.
     */
    public function getLcyAmountAttribute(): ?float
    {
        $lcy = LedgerSemantics::toLcy(
            $this->amount,
            $this->ledger_semantics_version,
            $this->document_type,
            $this->currency_factor,
            $this->currency_code,
        );

        return $lcy === null ? null : (float) $lcy;
    }

    /**
     * Signed LCY open exposure: the control-account measure for this row.
     */
    public function getSignedLcyRemainingAmountAttribute(): float
    {
        return LedgerSemantics::signedRemaining(
            $this->is_debit_entry,
            $this->is_credit_entry,
            $this->lcy_amount ?? $this->amount,
            $this->lcy_remaining_amount ?? 0.0,
        );
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
    public function applyToEntries(array $applications): float
    {
        return DB::transaction(function () use ($applications): float {
            return $this->applyToEntriesLocked($applications);
        });
    }

    /**
     * @param  array<int, array{entry_id:int, amount:float|int|string}>  $applications
     */
    private function applyToEntriesLocked(array $applications): float
    {
        if (! in_array($this->document_type, ['PAYMENT', 'BANK_TRANSFER', 'PURCHASE_CREDIT_MEMO'], true)) {
            throw new \Exception('Only vendor payment or credit memo entries can be applied');
        }

        $creditEntry = self::query()
            ->lockForUpdate()
            ->findOrFail($this->getKey());

        $normalizedApplications = $this->normalizeApplicationEntries($applications);

        $lockedEntryIds = collect($normalizedApplications)
            ->pluck('entry_id')
            ->map(fn ($entryId): int => (int) $entryId)
            ->push((int) $creditEntry->getKey())
            ->unique()
            ->sort()
            ->values();

        $lockedEntries = self::query()
            ->whereIn('id', $lockedEntryIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $creditEntry = $lockedEntries->get($creditEntry->getKey()) ?? $creditEntry;
        if (! in_array($creditEntry->document_type, ['PAYMENT', 'BANK_TRANSFER', 'PURCHASE_CREDIT_MEMO'], true)) {
            throw new \Exception('Only vendor payment or credit memo entries can be applied');
        }

        $creditVersionTwo = LedgerSemantics::isVersionTwo($creditEntry->ledger_semantics_version);

        $totalApplied = 0.0;
        $totalAppliedLcy = 0.0;
        $appliedEntries = $creditEntry->applied_to_entries ?? [];

        foreach ($normalizedApplications as $app) {
            $invoiceEntry = $lockedEntries->get((int) $app['entry_id']);

            if (! $invoiceEntry || ! $invoiceEntry->is_invoice) {
                throw new BusinessException('Vendor settlement target must be a vendor invoice ledger entry.');
            }

            if (! $invoiceEntry->open) {
                throw new BusinessException('Vendor invoice ledger entry is already settled.');
            }

            if ($creditEntry->business_id !== null && $invoiceEntry->business_id !== null
                && (int) $creditEntry->business_id !== (int) $invoiceEntry->business_id) {
                throw new BusinessException('Vendor settlement entries must belong to the same business.');
            }

            if (filled($creditEntry->currency_code) && filled($invoiceEntry->currency_code)
                && strtoupper((string) $creditEntry->currency_code) !== strtoupper((string) $invoiceEntry->currency_code)) {
                throw new BusinessException('Vendor settlement entries must use the same currency.');
            }

            if ($creditVersionTwo !== LedgerSemantics::isVersionTwo($invoiceEntry->ledger_semantics_version)) {
                throw new BusinessException('Vendor settlement entries must share the same ledger semantics version.');
            }

            if ($creditVersionTwo) {
                // Version-2 settlement is driven by the document-currency amount;
                // the base LCY carrying amount is released at each entry's own
                // recognition factor so both representations stay synchronized.
                if (DecimalMath::compare($creditEntry->currency_factor, $invoiceEntry->currency_factor) !== 0) {
                    throw new BusinessException('Vendor settlement entries must use the same recognition factor.');
                }

                $creditDocumentRemaining = (float) ($creditEntry->original_remaining_amount ?? 0);
                $invoiceDocumentRemaining = (float) ($invoiceEntry->original_remaining_amount ?? 0);

                $appliedDocumentAmount = min(
                    (float) $app['amount'],
                    max(0, $creditDocumentRemaining - $totalApplied),
                    $invoiceDocumentRemaining,
                );

                if ($appliedDocumentAmount <= 0) {
                    continue;
                }

                $invoiceLcyReleased = (float) LedgerSemantics::lcyFromDocument($appliedDocumentAmount, $invoiceEntry->currency_factor);
                $settlementLcyReleased = (float) LedgerSemantics::lcyFromDocument($appliedDocumentAmount, $creditEntry->currency_factor);

                $invoiceEntry->original_remaining_amount = max(0, $invoiceDocumentRemaining - $appliedDocumentAmount);
                $invoiceEntry->remaining_amount = max(0, (float) $invoiceEntry->remaining_amount - $invoiceLcyReleased);
                $invoiceEntry->open = $invoiceEntry->original_remaining_amount > 0.01;
                $invoiceEntry->save();

                $appliedEntries[] = [
                    'entry_id' => $invoiceEntry->id,
                    'document_number' => $invoiceEntry->document_number,
                    'amount' => $appliedDocumentAmount,
                    'lcy_amount' => $invoiceLcyReleased,
                    'applied_at' => now()->toDateTimeString(),
                ];

                $totalApplied += $appliedDocumentAmount;
                $totalAppliedLcy += $settlementLcyReleased;

                continue;
            }

            $applyAmount = min(
                (float) $app['amount'],
                (float) $creditEntry->remaining_amount - $totalApplied,
                (float) $invoiceEntry->remaining_amount
            );

            if ($applyAmount <= 0) {
                continue;
            }

            // Update invoice entry
            $invoiceEntry->remaining_amount = max(0, (float) $invoiceEntry->remaining_amount - $applyAmount);
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

        if ($creditVersionTwo) {
            $creditEntry->original_remaining_amount = max(0, (float) ($creditEntry->original_remaining_amount ?? 0) - $totalApplied);
            $creditEntry->remaining_amount = max(0, (float) $creditEntry->remaining_amount - $totalAppliedLcy);
            $creditEntry->applied_to_entries = $appliedEntries;
            $creditEntry->fully_applied = $creditEntry->original_remaining_amount <= 0.01;
            $creditEntry->open = ! $creditEntry->fully_applied;

            if ($creditEntry->fully_applied) {
                $creditEntry->original_remaining_amount = 0;
                $creditEntry->remaining_amount = 0;
            }

            $creditEntry->save();

            return $totalApplied;
        }

        // Update this entry
        $creditEntry->remaining_amount = max(0, (float) $creditEntry->remaining_amount - $totalApplied);
        $creditEntry->applied_to_entries = $appliedEntries;
        $creditEntry->fully_applied = $creditEntry->remaining_amount <= 0.01;
        $creditEntry->open = ! $creditEntry->fully_applied;

        if ($creditEntry->fully_applied) {
            $creditEntry->remaining_amount = 0;
        }

        $creditEntry->save();

        return $totalApplied;
    }

    /**
     * Normalize legacy/compatibility application payloads into ledger-entry ids.
     *
     * @param  array<int, array<string, float|int|string|null>>  $applications
     * @return array<int, array{entry_id:int, amount:float|int|string}>
     */
    private function normalizeApplicationEntries(array $applications): array
    {
        $normalized = [];

        foreach ($applications as $application) {
            $entryId = $this->resolveApplicationEntryId($application);

            if (! $entryId) {
                continue;
            }

            $normalized[] = [
                'entry_id' => $entryId,
                'amount' => $application['amount'] ?? 0,
            ];
        }

        return $normalized;
    }

    /**
     * Resolve legacy aliases to the canonical vendor ledger entry id.
     *
     * @param  array<string, float|int|string|null>  $application
     */
    private function resolveApplicationEntryId(array $application): ?int
    {
        foreach (['entry_id', 'ledger_entry_id', 'vendor_ledger_entry_id', 'target_ledger_entry_id'] as $key) {
            if (isset($application[$key]) && is_numeric($application[$key])) {
                return (int) $application[$key];
            }
        }

        foreach (['invoice_id', 'document_id'] as $key) {
            if (! isset($application[$key]) || ! is_numeric($application[$key])) {
                continue;
            }

            $purchaseInvoice = PostedPurchaseInvoice::query()->find((int) $application[$key])
                ?? PurchaseInvoice::query()->find((int) $application[$key]);

            if (! $purchaseInvoice) {
                continue;
            }

            $ledgerEntryId = self::query()
                ->where('document_type', 'PURCHASE_INVOICE')
                ->where('document_number', $purchaseInvoice->document_number)
                ->where('vendor_id', $this->vendor_id)
                ->value('id');

            if ($ledgerEntryId) {
                return (int) $ledgerEntryId;
            }
        }

        return null;
    }

    /**
     * Apply this credit memo to a specific invoice
     */
    public function applyToInvoice(PurchaseInvoice $invoice, ?float $amount = null): void
    {
        if (! $this->is_credit_memo && ! $this->is_payment) {
            throw new \Exception('Entry must be a credit memo or payment');
        }

        DB::transaction(function () use ($invoice, $amount): void {
            $lockedInvoice = PurchaseInvoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            $invoiceEntry = self::query()
                ->where('document_type', 'PURCHASE_INVOICE')
                ->where('document_number', $lockedInvoice->document_number)
                ->where('vendor_id', $this->vendor_id)
                ->lockForUpdate()
                ->first();

            if (! $invoiceEntry) {
                throw new \Exception('Invoice ledger entry not found');
            }

            $applyAmount = $amount ?? (float) $lockedInvoice->remaining_amount;

            $appliedAmount = $this->applyToEntriesLocked([[
                'entry_id' => $invoiceEntry->id,
                'amount' => $applyAmount,
            ]]);

            $newAmountPaid = (float) $lockedInvoice->amount_paid + $appliedAmount;
            $newRemaining = (float) $lockedInvoice->grand_total - $newAmountPaid;

            if ($newRemaining <= 0.01) {
                $lockedInvoice->update([
                    'paid_in_full' => true,
                    'paid_in_full_date' => now(),
                    'remaining_amount' => 0,
                    'amount_paid' => $newAmountPaid,
                ]);

                return;
            }

            $lockedInvoice->update([
                'paid_in_full' => false,
                'paid_in_full_date' => null,
                'remaining_amount' => $newRemaining,
                'amount_paid' => $newAmountPaid,
            ]);
        });
    }

    /**
     * Reverse this entry (creates correcting entry)
     */
    public function reverse(int $userId, string $reason): self
    {
        return DB::transaction(function () use ($userId, $reason): self {
            $original = self::query()->lockForUpdate()->findOrFail($this->getKey());

            if ($original->reversed) {
                throw new \Exception('Entry is already reversed');
            }

            $originalGlEntries = $original->reversalGlEntries();
            $originalGlEntry = $originalGlEntries->first();
            if (! $originalGlEntry) {
                throw new \Exception('Cannot reverse a vendor ledger entry without its original G/L transaction.');
            }

            if ($original->applied_to_entries) {
                $original->unapplyAll();
            }

            $reversalDocumentNumber = Str::limit('REV-'.$original->document_number, 20, '');
            $reversalEntryNumber = self::getNextEntryNumber($original->vendor_id);
            $reversalAmount = (float) -$original->amount;

            $reversalTransaction = app(GeneralLedgerService::class)->postTransaction(
                $originalGlEntries->map(fn (GlEntry $entry): array => [
                    'account_id' => $entry->chart_of_account_id,
                    'debit_amount' => $entry->credit_amount,
                    'credit_amount' => $entry->debit_amount,
                    'description' => "Reversal of {$original->document_number}: {$reason}",
                    'source_type' => 'VENDOR',
                    'source_number' => $reversalDocumentNumber,
                    'vendor_ledger_entry_id' => $original->id,
                ])->all(),
                [
                    'source_module' => 'finance',
                    'source_type' => 'VENDOR',
                    'source_id' => $original->vendor_id,
                    'source_number' => $reversalDocumentNumber,
                    'document_type' => 'VENDOR_LEDGER_REVERSAL',
                    'document_number' => $reversalDocumentNumber,
                    'posting_date' => now(),
                    'description' => "Reversal of {$original->document_number}: {$reason}",
                    'currency_code' => $original->currency_code,
                    'exchange_rate' => $original->currency_factor,
                    'dimensions' => $original->dimensions ?? [],
                    'actor_id' => $userId,
                    'reversal_of_transaction_id' => $originalGlEntry->posting_transaction_id,
                    'idempotency_key' => 'vendor-ledger-reversal:'.$original->id,
                    'transaction_key' => 'vendor-ledger-reversal:'.$original->id,
                ]
            );

            $reversal = self::create([
                'entry_number' => $reversalEntryNumber,
                'vendor_id' => $original->vendor_id,
                'business_id' => $original->business_id,
                'document_type' => 'ADJUSTMENT',
                'document_number' => $reversalDocumentNumber,
                'description' => "Reversal of {$original->document_number}: {$reason}",
                'posting_date' => now(),
                'document_date' => now(),
                'debit_amount' => $original->credit_amount,
                'credit_amount' => $original->debit_amount,
                'amount' => -$original->amount,
                'running_balance' => self::runningBalanceForNewRow(
                    $original->vendor_id,
                    $reversalEntryNumber,
                    $reversalAmount,
                    $original->ledger_semantics_version,
                ),
                'remaining_amount' => 0,
                'open' => false,
                'currency_code' => $original->currency_code,
                'original_debit_amount' => $original->original_credit_amount,
                'original_credit_amount' => $original->original_debit_amount,
                'original_remaining_amount' => 0,
                'currency_factor' => $original->currency_factor,
                'ledger_semantics_version' => $original->ledger_semantics_version,
                'general_business_posting_group_id' => $original->general_business_posting_group_id,
                'vendor_posting_group_id' => $original->vendor_posting_group_id,
                'gl_entry_id' => $reversalTransaction->glEntries->firstWhere('chart_of_account_id', $originalGlEntry->chart_of_account_id)?->id,
                'source_id' => $original->source_id,
                'source_type' => $original->source_type,
                'created_by' => $userId,
                'dimensions' => $original->dimensions,
            ]);

            $original->update([
                'reversed' => true,
                'reversed_at' => now(),
                'reversed_by' => $userId,
                'reversal_entry_number' => $reversal->entry_number,
                'remaining_amount' => 0,
                'original_remaining_amount' => 0,
                'open' => false,
            ]);

            return $reversal;
        });
    }

    /** @return Collection<int, GlEntry> */
    private function reversalGlEntries(): Collection
    {
        $query = GlEntry::query()->orderBy('id');
        if ($this->gl_entry_id && ($entry = GlEntry::query()->find($this->gl_entry_id))?->posting_transaction_id) {
            return $query->where('posting_transaction_id', $entry->posting_transaction_id)->get();
        }

        return $query->where('document_number', $this->document_number)
            ->whereIn('document_type', ['PURCHASE_INVOICE', 'PURCHASE_CREDIT_MEMO', 'PAYMENT'])
            ->get();
    }

    /**
     * Unapply all applications (for reversal)
     */
    protected function unapplyAll(): void
    {
        foreach ($this->applied_to_entries ?? [] as $app) {
            $invoiceEntry = self::find($app['entry_id']);
            if ($invoiceEntry) {
                $restore = (float) ($app['amount'] ?? 0);

                if (LedgerSemantics::isVersionTwo($invoiceEntry->ledger_semantics_version)) {
                    $lcyRestore = array_key_exists('lcy_amount', $app)
                        ? (float) $app['lcy_amount']
                        : (float) LedgerSemantics::lcyFromDocument($restore, $invoiceEntry->currency_factor);

                    $invoiceEntry->original_remaining_amount = (float) ($invoiceEntry->original_remaining_amount ?? 0) + $restore;
                    $invoiceEntry->remaining_amount = (float) $invoiceEntry->remaining_amount + $lcyRestore;
                    $invoiceEntry->open = $invoiceEntry->remaining_amount > 0.01;
                } else {
                    $invoiceEntry->remaining_amount += $restore;
                    $invoiceEntry->open = true;
                }

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
     * Prospective-safe (version-2) LCY running balance.
     *
     * The running balance of a version-2 row is the LCY-normalized sum of the
     * vendor's trusted preceding rows plus this row's own LCY amount, evaluated
     * with deterministic decimal arithmetic. Preceding rows are normalized
     * through the shared {@see LedgerSemantics} rules, so a legacy
     * document-currency row contributes its LCY carrying amount and cannot be
     * mixed into the total at face value.
     *
     * A predecessor that cannot be normalized under those rules (no usable
     * factor) is never fabricated into LCY. In that case the returned value is
     * this row's own LCY amount and `authoritative` is false: a partial or
     * mixed-unit total is never presented as a vendor running balance. No
     * historical row is rewritten and the NOT NULL column keeps its schema.
     *
     * @return array{value: float, authoritative: bool}
     */
    public static function calculateLcyRunningBalance(int $vendorId, int $beforeEntryNumber, float $currentLcyAmount): array
    {
        $preceding = self::forVendor($vendorId)
            ->where('entry_number', '<', $beforeEntryNumber)
            ->get(['amount', 'currency_factor', 'currency_code', 'ledger_semantics_version', 'document_type']);

        $sum = '0';

        foreach ($preceding as $row) {
            $lcy = $row->lcy_amount;

            if ($lcy === null) {
                return [
                    'value' => (float) DecimalMath::toScale($currentLcyAmount, DecimalPrecision::AMOUNT_SCALE),
                    'authoritative' => false,
                ];
            }

            $sum = DecimalMath::add($sum, $lcy, DecimalPrecision::AMOUNT_SCALE);
        }

        $sum = DecimalMath::add($sum, $currentLcyAmount, DecimalPrecision::AMOUNT_SCALE);

        return ['value' => (float) $sum, 'authoritative' => true];
    }

    /**
     * Running balance for a newly created row, selecting the contract that
     * matches the row's own semantics.
     *
     * Version-2 rows use the LCY-normalized contract; legacy rows keep the
     * historical raw-increment behaviour unchanged.
     */
    protected static function runningBalanceForNewRow(int $vendorId, int $entryNumber, float $currentAmount, mixed $version): float
    {
        if (LedgerSemantics::isVersionTwo($version)) {
            return self::calculateLcyRunningBalance($vendorId, $entryNumber, $currentAmount)['value'];
        }

        return self::calculateNewBalance($vendorId, $currentAmount);
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
        $documentAmount = abs((float) $invoice->grand_total);
        $factor = LedgerSemantics::normalizeFactor($invoice->currency_code, $invoice->currency_factor);
        $amount = (float) LedgerSemantics::lcyFromDocument($documentAmount, $factor);
        $signedAmount = $amount; // Positive vendor balance for credit-side payable exposure
        $entryNumber = self::getNextEntryNumber($invoice->vendor_id);

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
            'entry_number' => $entryNumber,
            'vendor_id' => $invoice->vendor_id,
            'business_id' => $invoice->business_id,
            'document_type' => 'PURCHASE_INVOICE',
            'document_number' => $invoice->document_number,
            'external_document_number' => $invoice->external_document_number,
            'description' => "Invoice {$invoice->document_number}",
            'posting_date' => $invoice->posting_date,
            'document_date' => $invoice->document_date,
            'due_date' => $invoice->due_date,
            'debit_amount' => 0,
            'credit_amount' => $amount,
            'amount' => $signedAmount,
            'running_balance' => self::calculateLcyRunningBalance($invoice->vendor_id, $entryNumber, $signedAmount)['value'],
            'remaining_amount' => $amount,
            'open' => true,
            'currency_code' => $invoice->currency_code,
            'original_debit_amount' => 0,
            'original_credit_amount' => $documentAmount,
            'original_remaining_amount' => $documentAmount,
            'currency_factor' => $factor,
            'ledger_semantics_version' => LedgerSemantics::VERSION_LCY_BASE,
            'general_business_posting_group_id' => $invoice->general_business_posting_group_id,
            'vendor_posting_group_id' => $invoice->vendor_posting_group_id,
            'gl_entry_id' => GlEntry::query()
                ->where('document_type', 'PURCHASE_INVOICE')
                ->where('document_number', $invoice->document_number)
                ->where('chart_of_account_id', $invoice->vendor?->vendorPostingGroup?->payables_account_id)
                ->orderBy('id')
                ->value('id'),
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
        $documentAmount = abs((float) $creditMemo->grand_total);
        $factor = LedgerSemantics::normalizeFactor($creditMemo->currency_code, $creditMemo->currency_factor);
        $amount = (float) LedgerSemantics::lcyFromDocument($documentAmount, $factor);
        $signedAmount = -$amount; // Negative balance impact reduces vendor payable
        $entryNumber = self::getNextEntryNumber($creditMemo->vendor_id);

        return self::create([
            'entry_number' => $entryNumber,
            'vendor_id' => $creditMemo->vendor_id,
            'business_id' => $creditMemo->business_id,
            'document_type' => 'PURCHASE_CREDIT_MEMO',
            'document_number' => $creditMemo->document_number,
            'external_document_number' => $creditMemo->external_document_number,
            'description' => "Credit Memo {$creditMemo->document_number}",
            'posting_date' => $creditMemo->posting_date,
            'document_date' => $creditMemo->document_date,
            'due_date' => null, // Credit memos don't have due dates
            'debit_amount' => $amount,
            'credit_amount' => 0,
            'amount' => $signedAmount,
            'running_balance' => self::calculateLcyRunningBalance($creditMemo->vendor_id, $entryNumber, $signedAmount)['value'],
            'remaining_amount' => $amount,
            'open' => true,
            'currency_code' => $creditMemo->currency_code,
            'original_debit_amount' => $documentAmount,
            'original_credit_amount' => 0,
            'original_remaining_amount' => $documentAmount,
            'currency_factor' => $factor,
            'ledger_semantics_version' => LedgerSemantics::VERSION_LCY_BASE,
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
        $documentAmount = abs((float) $payment->payment_amount);
        $factor = LedgerSemantics::normalizeFactor($payment->currency_code, $payment->currency_factor);
        $lcyAmount = (float) LedgerSemantics::lcyFromDocument($documentAmount, $factor);
        $amount = -$lcyAmount; // Negative (reduces AP)
        $entryNumber = self::getNextEntryNumber($payment->party_id);

        return self::create([
            'entry_number' => $entryNumber,
            'vendor_id' => $payment->party_id,
            'business_id' => $payment->business_id,
            'document_type' => 'PAYMENT',
            'document_number' => $payment->payment_number,
            'external_document_number' => $payment->external_reference,
            'description' => "Payment {$payment->payment_number} - {$payment->payment_method}",
            'posting_date' => $payment->posting_date,
            'document_date' => $payment->payment_date,
            'due_date' => null, // Payments don't have due dates
            'debit_amount' => $lcyAmount,
            'credit_amount' => 0,
            'amount' => $amount,
            'running_balance' => self::calculateLcyRunningBalance($payment->party_id, $entryNumber, $amount)['value'],
            'remaining_amount' => 0, // Payments are always closed
            'open' => false,
            'currency_code' => $payment->currency_code,
            'original_debit_amount' => $documentAmount,
            'original_credit_amount' => 0,
            'original_remaining_amount' => 0,
            'currency_factor' => $factor,
            'ledger_semantics_version' => LedgerSemantics::VERSION_LCY_BASE,
            'general_business_posting_group_id' => $payment->general_business_posting_group_id,
            'vendor_posting_group_id' => $payment->posting_group_id,
            'gl_entry_id' => $payment->glEntries()->first()?->id,
            'source_id' => $payment->id,
            'source_type' => Payment::class,
            'created_by' => $payment->created_by,
        ]);
    }

    /**
     * Create payment entry (legacy method - prefer createFromPayment).
     *
     * Legacy, non-versioned factory: it has no currency context and therefore
     * never stamps version-2 semantics. It must not be used where LCY-base
     * (version 2) vendor ledger semantics are expected; use
     * {@see self::createFromPayment()} instead. It is retained for backwards
     * compatibility because it is public API.
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
            'debit_amount' => $amount,
            'credit_amount' => 0,
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
        // Reversal rows are append-only; the original and correction must net.
        $query = self::forVendor($vendorId);

        if ($asOf) {
            $query->where('posting_date', '<=', $asOf);
        }

        return (float) $query->sum(DB::raw(LedgerSemantics::lcyAmountSql('vendor_ledger_entries')));
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
            if (! $entry->is_invoice) {
                continue;
            }

            $lcyRemaining = $entry->lcy_remaining_amount;

            if ($lcyRemaining === null) {
                continue;
            }

            $category = $entry->aging_category;
            $aging[$category] += $lcyRemaining;
            $aging['TOTAL'] += $lcyRemaining;
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
