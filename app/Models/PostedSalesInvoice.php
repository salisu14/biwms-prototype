<?php
// app/Models/PostedSalesInvoice.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostedSalesInvoice extends Model
{
    use HasFactory;

    protected $table = 'posted_sales_invoices';

    protected $fillable = [
        'document_number',
        'external_document_number',
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
        'shipping_agent_code',
        'posting_date',
        'document_date',
        'due_date',
        'vat_date',
        'shipment_date',
        'subtotal',
        'line_discount_total',
        'invoice_discount_amount',
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
        'salesperson_id',
        'cancelled',
        'cancelled_at',
        'cancelled_by',
        'corrective_document_number',
        'dimensions',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'document_date' => 'date',
        'due_date' => 'date',
        'vat_date' => 'date',
        'shipment_date' => 'date',
        'subtotal' => 'decimal:4',
        'line_discount_total' => 'decimal:4',
        'invoice_discount_amount' => 'decimal:4',
        'total_amount' => 'decimal:4',
        'total_vat' => 'decimal:4',
        'grand_total' => 'decimal:4',
        'currency_factor' => 'decimal:6',
        'amount_paid' => 'decimal:4',
        'remaining_amount' => 'decimal:4',
        'paid_in_full' => 'boolean',
        'paid_in_full_date' => 'datetime',
        'posted_at' => 'datetime',
        'cancelled' => 'boolean',
        'cancelled_at' => 'datetime',
        'dimensions' => 'array',
    ];

    // ==================== RELATIONSHIPS ====================

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
        return $this->hasMany(PostedSalesInvoiceLine::class, 'posted_sales_invoice_id')
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

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
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
            ->where('document_type', 'SALES_INVOICE');
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

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('posting_date', [$startDate, $endDate]);
    }

    // ==================== CALCULATED ATTRIBUTES ====================

    public function getIsOverdueAttribute(): bool
    {
        return !$this->paid_in_full && $this->due_date < now();
    }

    public function getDaysOverdueAttribute(): ?int
    {
        if (!$this->is_overdue) return null;
        return $this->due_date->diffInDays(now());
    }

    public function getStatusAttribute(): string
    {
        if ($this->cancelled) return 'CANCELLED';
        if ($this->paid_in_full) return 'PAID';
        if ($this->is_overdue) return 'OVERDUE';
        return 'OPEN';
    }

    public function getTotalProfitAttribute(): float
    {
        return $this->lines->sum('profit_amount');
    }

    public function getProfitMarginPercentAttribute(): float
    {
        if ($this->total_amount == 0) return 0;
        return ($this->total_profit / $this->total_amount) * 100;
    }

    // ==================== BUSINESS METHODS ====================

    /**
     * Record a payment
     */
    public function applyPayment(float $amount, \DateTime $paymentDate): void
    {
        $this->amount_paid += $amount;
        $this->remaining_amount = $this->grand_total - $this->amount_paid;

        if ($this->remaining_amount <= 0.01) {
            $this->paid_in_full = true;
            $this->paid_in_full_date = $paymentDate;
            $this->remaining_amount = 0;
        }

        $this->save();
    }

    /**
     * Cancel invoice (creates credit memo)
     */
    public function cancel(int $userId, string $reason): PostedSalesCreditMemo
    {
        if ($this->cancelled) {
            throw new \Exception('Invoice is already cancelled');
        }

        return \DB::transaction(function () use ($userId, $reason) {
            // Create credit memo
            $creditMemo = PostedSalesCreditMemo::create([
                'document_number' => PostedSalesCreditMemo::generateNumber(),
                'corrected_invoice_id' => $this->id,
                'corrected_invoice_number' => $this->document_number,
                'customer_id' => $this->customer_id,
                'customer_name' => $this->customer_name,
                'posting_date' => now(),
                'total_amount' => -$this->total_amount,
                'total_vat' => -$this->total_vat,
                'grand_total' => -$this->grand_total,
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);

            // Reverse G/L entries
            foreach ($this->lines as $line) {
                PostedSalesCreditMemoLine::create([
                    'posted_sales_credit_memo_id' => $creditMemo->id,
                    'item_id' => $line->item_id,
                    'item_code' => $line->item_code,
                    'item_description' => $line->item_description,
                    'quantity' => -$line->quantity,
                    'unit_price' => $line->unit_price,
                    'line_amount' => -$line->line_amount,
                    'vat_amount' => -$line->vat_amount,
                    'amount_including_vat' => -$line->amount_including_vat,
                ]);
            }

            // Mark invoice cancelled
            $this->update([
                'cancelled' => true,
                'cancelled_at' => now(),
                'cancelled_by' => $userId,
                'corrective_document_number' => $creditMemo->document_number,
            ]);

            return $creditMemo;
        });
    }

    /**
     * Generate document number
     */
    public static function generateNumber(): string
    {
        $prefix = 'SI';
        $year = date('Y');
        $count = self::whereYear('posted_at', $year)->count() + 1;
        return sprintf('%s-%d-%06d', $prefix, $year, $count);
    }
}
