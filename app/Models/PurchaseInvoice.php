<?php

// app/Models/PurchaseInvoice.php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseInvoice extends Model
{
    use HasFactory;

    protected $table = 'purchase_invoices';

    protected $fillable = [
        'document_number',
        'external_document_number',
        'order_id',
        'order_number',
        'vendor_id',
        'vendor_name',
        'vendor_address',
        'general_business_posting_group_id',
        'vendor_posting_group_id',
        'vat_bus_posting_group',
        'location_id',
        'posting_date',
        'document_date',
        'due_date',
        'status',
        'vat_date',
        'total_amount',
        'total_vat',
        'grand_total',
        'currency_code',
        'currency_factor',
        'amount_paid',
        'remaining_amount',
        'paid_in_full',
        'paid_in_full_date',
        'posted_by',
        'posted_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'cancelled',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'corrective_document_number',
        'dimensions',
    ];

    protected $casts = [
        'status' => ApprovalStatus::class,
        'posting_date' => 'date',
        'document_date' => 'date',
        'due_date' => 'date',
        'vat_date' => 'date',
        'total_amount' => 'decimal:4',
        'total_vat' => 'decimal:4',
        'grand_total' => 'decimal:4',
        'currency_factor' => 'decimal:6',
        'amount_paid' => 'decimal:4',
        'remaining_amount' => 'decimal:4',
        'paid_in_full' => 'boolean',
        'paid_in_full_date' => 'datetime',
        'posted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled' => 'boolean',
        'cancelled_at' => 'datetime',
        'dimensions' => 'array',
    ];

    // ==================== RELATIONSHIPS ====================

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'order_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceLine::class, 'purchase_invoice_id')
            ->orderBy('line_number');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function generalBusinessPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralBusinessPostingGroup::class);
    }

    public function vendorPostingGroup(): BelongsTo
    {
        return $this->belongsTo(VendorPostingGroup::class);
    }

    // Related G/L Entries
    public function glEntries(): HasMany
    {
        return $this->hasMany(GlEntry::class, 'source_number', 'document_number')
            ->where('source_type', 'VENDOR')
            ->where('document_type', 'PURCHASE_INVOICE');
    }

    // ==================== SCOPES ====================

    public function scopeNotCancelled($query)
    {
        return $query->where('cancelled', false);
    }

    public function scopeOpen($query)
    {
        return $query->where('paid_in_full', false)
            ->where('cancelled', false);
    }

    public function scopeOverdue($query)
    {
        return $query->where('paid_in_full', false)
            ->where('due_date', '<', now());
    }

    public function scopeForVendor($query, int $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('posting_date', [$startDate, $endDate]);
    }

    // ==================== CALCULATED ATTRIBUTES ====================

    public function getIsOverdueAttribute(): bool
    {
        return ! $this->paid_in_full && $this->due_date < now();
    }

    public function getDaysOverdueAttribute(): ?int
    {
        if (! $this->is_overdue) {
            return null;
        }

        return $this->due_date->diffInDays(now());
    }

    public function getPaymentStatusAttribute(): string
    {
        if ($this->cancelled) {
            return 'CANCELLED';
        }
        if ($this->paid_in_full) {
            return 'PAID';
        }
        if ($this->is_overdue) {
            return 'OVERDUE';
        }

        return 'OPEN';
    }

    // ==================== BUSINESS METHODS ====================

    /**
     * Record a payment against this invoice
     */
    public function applyPayment(float $amount, \DateTime $paymentDate): void
    {
        $this->amount_paid += $amount;
        $this->remaining_amount = $this->grand_total - $this->amount_paid;

        if ($this->remaining_amount <= 0.01) { // Allow for rounding
            $this->paid_in_full = true;
            $this->paid_in_full_date = $paymentDate;
            $this->remaining_amount = 0;
        }

        $this->save();
    }

    /**
     * Cancel this invoice (creates credit memo)
     */
    public function cancel(int $userId, string $reason): PostedPurchaseCreditMemo
    {
        if ($this->cancelled) {
            throw new \Exception('Invoice is already cancelled');
        }

        return \DB::transaction(function () use ($userId, $reason) {
            // Create credit memo (reversing entries)
            $creditMemo = PostedPurchaseCreditMemo::create([
                'document_number' => PostedPurchaseCreditMemo::generateNumber(),
                'corrected_invoice_id' => $this->id,
                'corrected_invoice_number' => $this->document_number,
                'vendor_id' => $this->vendor_id,
                'vendor_name' => $this->vendor_name,
                'posting_date' => now(),
                'total_amount' => -$this->total_amount,
                'total_vat' => -$this->total_vat,
                'grand_total' => -$this->grand_total,
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);

            // Reverse G/L entries
            foreach ($this->lines as $line) {
                // Create credit memo line
                $creditMemo->lines()->create([
                    'line_number' => $line->line_number,
                    'item_id' => $line->item_id,
                    'item_code' => $line->item_code,
                    'item_description' => $line->item_description,
                    'quantity' => -$line->quantity,
                    'unit_cost' => $line->unit_cost,
                    'line_total' => -$line->line_total,
                    'vat_amount' => -$line->vat_amount,
                    'amount_including_vat' => -$line->amount_including_vat,
                ]);

                // Post reversing G/L entry
                // (Implementation depends on your PostingService)
            }

            // Mark invoice as cancelled
            $this->update([
                'cancelled' => true,
                'cancelled_at' => now(),
                'cancelled_by' => $userId,
                'cancellation_reason' => $reason,
                'corrective_document_number' => $creditMemo->document_number,
            ]);

            return $creditMemo;
        });
    }

    /**
     * Generate next document number
     */
    public static function generateNumber(): string
    {
        $prefix = 'PI'; // Purchase Invoice
        $year = date('Y');
        $count = self::whereYear('posted_at', $year)->count() + 1;

        return sprintf('%s-%d-%06d', $prefix, $year, $count);
    }

    public function isPosted(): bool
    {
        return $this->status === ApprovalStatus::POSTED || $this->posted_at !== null;
    }
}
