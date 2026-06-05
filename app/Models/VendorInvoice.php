<?php

namespace App\Models;

use App\Models\Manufacturing\CapExProject;
use App\Services\NumberSeriesService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class VendorInvoice extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (VendorInvoice $invoice): void {
            if (empty($invoice->document_number)) {
                $invoice->document_number = self::generateDocumentNumber();
            }
        });
    }

    protected $fillable = [
        'document_number',
        'external_document_no',
        'vendor_id',
        'vendor_invoice_no',
        'vendor_invoice_date',
        'document_type',
        'status',
        'amount',
        'discount_amount',
        'tax_amount',
        'amount_including_tax',
        'currency_code',
        'exchange_rate',
        'amount_lcy',
        'posting_date',
        'due_date',
        'receipt_date',
        'payment_terms_code',
        'payment_method_code',
        'payable_gl_account_id',
        'expense_gl_account_id',
        'source_document_type',
        'source_document_id',
        'source_document_no',
        'shortcut_dimension_1_code',
        'shortcut_dimension_2_code',
        'dimension_set_id',
        'requested_by',
        'approved_by',
        'approved_at',
        'posted',
        'posted_at',
        'posted_by',
        'remaining_amount',
        'last_payment_date',
        'capex_project_id',
        'capitalized',
        'description',
        'internal_notes',
        'created_by',
        'last_modified_by',
    ];

    protected $casts = [
        'vendor_invoice_date' => 'date',
        'posting_date' => 'date',
        'due_date' => 'date',
        'receipt_date' => 'date',
        'approved_at' => 'datetime',
        'posted_at' => 'datetime',
        'last_payment_date' => 'date',
        'amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'amount_including_tax' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'amount_lcy' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'posted' => 'boolean',
        'capitalized' => 'boolean',
    ];

    // Relationships

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(VendorInvoiceLine::class);
    }

    public function capExProject(): BelongsTo
    {
        return $this->belongsTo(CapExProject::class, 'capex_project_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastModifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by');
    }

    public function payableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'payable_gl_account_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_gl_account_id');
    }

    // Polymorphic source document
    public function sourceDocument(): ?Model
    {
        return match ($this->source_document_type) {
            'PURCHASE_ORDER' => PurchaseOrder::find($this->source_document_id),
            'PURCHASE_RECEIPT' => PurchaseReceipt::find($this->source_document_id),
            'BLANKET_ORDER' => BlanketOrder::find($this->source_document_id),
            default => null,
        };
    }

    // Scopes

    public function scopeOpen($query)
    {
        return $query->where('status', 'OPEN');
    }

    public function scopePosted($query)
    {
        return $query->where('posted', true);
    }

    public function scopeUnposted($query)
    {
        return $query->where('posted', false);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->where('remaining_amount', '>', 0);
    }

    public function scopeForCapEx($query)
    {
        return $query->whereNotNull('capex_project_id');
    }

    public function scopeByVendor($query, int $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    // Business Logic

    /**
     * Calculate days overdue
     */
    public function getDaysOverdue(): ?int
    {
        if ($this->remaining_amount <= 0 || ! $this->due_date) {
            return null;
        }

        return max(0, now()->diffInDays($this->due_date, false) * -1);
    }

    /**
     * Check if invoice is fully paid
     */
    public function isFullyPaid(): bool
    {
        return $this->remaining_amount <= 0;
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(): string
    {
        if ($this->isFullyPaid()) {
            return 'PAID';
        }
        if ($this->remaining_amount < $this->amount_including_tax) {
            return 'PARTIAL';
        }

        return 'UNPAID';
    }

    /**
     * Approve invoice
     */
    public function approve(int $approverId): void
    {
        $this->update([
            'status' => 'APPROVED',
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);
    }

    /**
     * Post invoice to GL (integrates with CapEx if applicable)
     */
    public function post(int $userId): void
    {
        if ($this->posted) {
            throw new \Exception('Invoice already posted');
        }

        DB::transaction(function () use ($userId) {
            // If linked to CapEx project, handle capitalization
            if ($this->capex_project_id && ! $this->capitalized) {
                $this->capitalizeToProject();
            }

            // Standard GL posting
            // $this->createGlEntries();

            $this->update([
                'posted' => true,
                'posted_at' => now(),
                'posted_by' => $userId,
                'status' => 'POSTED',
                'remaining_amount' => $this->amount_including_tax,
            ]);
        });
    }

    /**
     * Capitalize invoice costs to CapEx project
     */
    protected function capitalizeToProject(): void
    {
        $project = $this->capExProject;
        if (! $project) {
            return;
        }

        foreach ($this->lines as $line) {
            $eligible = $this->isLineEligibleForCapitalization($line);

            $project->lines()->create([
                'line_number' => $project->getNextLineNumber(),
                'line_type' => $this->mapInvoiceTypeToCapExType($line->type),
                'description' => $line->description,
                'actual_amount' => $line->line_amount,
                'source_document_type' => 'VENDOR_INVOICE',
                'source_document_id' => $this->id,
                'source_document_no' => $this->document_number,
                'source_document_date' => $this->posting_date,
                'vendor_id' => $this->vendor_id,
                'purchase_order_number' => $line->purchase_order_id ? "PO-{$line->purchase_order_id}" : null,
                'eligible_for_capitalization' => $eligible,
                'non_capitalization_reason' => $eligible ? null : 'Below threshold or non-capitalizable type',
                'status' => 'INVOICED',
            ]);
        }

        $project->recalculateActualAmount();

        $this->update(['capitalized' => true]);
    }

    /**
     * Determine if invoice line is eligible for capitalization
     */
    protected function isLineEligibleForCapitalization($line): bool
    {
        $project = $this->capExProject;
        if (! $project) {
            return false;
        }

        // Check threshold
        if ($line->line_amount < $project->capitalization_threshold) {
            return false;
        }

        // Check type eligibility
        return match ($line->type) {
            'ITEM', 'FIXED_ASSET' => true,
            'GL_ACCOUNT' => $this->isGlAccountCapitalizable($line->gl_account_id),
            'CHARGE' => false,
            default => false,
        };
    }

    /**
     * Map invoice line type to CapEx project line type
     */
    protected function mapInvoiceTypeToCapExType(string $invoiceType): string
    {
        return match ($invoiceType) {
            'ITEM' => 'MATERIAL',
            'FIXED_ASSET' => 'TOOLING',
            'GL_ACCOUNT' => 'EXTERNAL_SERVICE',
            'CHARGE' => 'OVERHEAD',
            default => 'EXTERNAL_SERVICE',
        };
    }

    /**
     * Check if GL account is capitalizable (would check account type)
     */
    protected function isGlAccountCapitalizable(?int $glAccountId): bool
    {
        if (! $glAccountId) {
            return false;
        }

        // Would check if account is in capitalizable range
        // return GlAccount::find($glAccountId)?->is_capitalizable ?? false;
        return true; // Simplified
    }

    /**
     * Apply payment to invoice
     */
    public function applyPayment(float $amount, \DateTime $paymentDate): void
    {
        $newRemaining = max(0, $this->remaining_amount - $amount);

        $this->update([
            'remaining_amount' => $newRemaining,
            'last_payment_date' => $paymentDate,
            'status' => $newRemaining <= 0 ? 'PAID' : $this->status,
        ]);
    }

    /**
     * Generate unique document number
     */
    public static function generateDocumentNumber(): string
    {
        $seriesService = app(NumberSeriesService::class);

        foreach (['V-INV', 'VENDOR_INVOICE', 'VI'] as $seriesCode) {
            $nextNumber = $seriesService->tryGetNextNo($seriesCode);

            if (! empty($nextNumber)) {
                return $nextNumber;
            }
        }

        $prefix = 'VI';
        $year = date('Y');
        $sequence = static::whereYear('created_at', $year)->count() + 1;

        return "{$prefix}-{$year}-".str_pad($sequence, 6, '0', STR_PAD_LEFT);
    }
}
