<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostedPurchaseInvoice extends Model
{
    use HasFactory;

    protected $table = 'posted_purchase_invoices';

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
        'vat_business_posting_group_id',
        'location_id',
        'posting_date',
        'document_date',
        'due_date',
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
        'cancelled',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'corrective_document_number',
        'dimensions',
    ];

    protected $casts = [
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
        'cancelled' => 'boolean',
        'cancelled_at' => 'datetime',
        'dimensions' => 'array',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PostedPurchaseInvoiceLine::class, 'posted_purchase_invoice_id')->orderBy('line_number');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'order_id');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function glEntries(): HasMany
    {
        return $this->hasMany(GlEntry::class, 'source_number', 'document_number')
            ->where('source_type', 'VENDOR')
            ->where('document_type', 'PURCHASE_INVOICE');
    }

    public function getStatusAttribute(): string
    {
        if ($this->cancelled) {
            return 'CANCELLED';
        }

        if ($this->paid_in_full) {
            return 'PAID';
        }

        if ($this->due_date && $this->due_date->isPast() && (float) $this->remaining_amount > 0.01) {
            return 'OVERDUE';
        }

        return 'OPEN';
    }

    public function scopeForVendor($query, int $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }
}
