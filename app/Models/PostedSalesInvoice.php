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
        return ! $this->paid_in_full && $this->due_date < now();
    }

    public function getDaysOverdueAttribute(): ?int
    {
        if (! $this->is_overdue) {
            return null;
        }

        return $this->due_date->diffInDays(now());
    }

    public function getStatusAttribute(): string
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

    public function getTotalProfitAttribute(): float
    {
        return $this->lines->sum('profit_amount');
    }

    public function getProfitMarginPercentAttribute(): float
    {
        if ($this->total_amount == 0) {
            return 0;
        }

        return ($this->total_profit / $this->total_amount) * 100;
    }

    public function isPosted(): bool
    {
        return true;
    }
}
