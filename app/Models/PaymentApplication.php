<?php

// app/Models/PaymentApplication.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'document_type',
        'document_id',
        'document_number',
        'document_original_amount',
        'document_remaining_before',
        'amount_applied',
        'discount_applied',
        'write_off_amount',
        'document_remaining_after',
        'full_payment',
        'applied_by',
        'applied_at',
        'reversed',
        'reversed_at',
        'reversed_by',
    ];

    protected $casts = [
        'document_original_amount' => 'decimal:4',
        'document_remaining_before' => 'decimal:4',
        'amount_applied' => 'decimal:4',
        'discount_applied' => 'decimal:4',
        'write_off_amount' => 'decimal:4',
        'document_remaining_after' => 'decimal:4',
        'full_payment' => 'boolean',
        'applied_at' => 'datetime',
        'reversed' => 'boolean',
        'reversed_at' => 'datetime',
    ];

    // ==================== RELATIONSHIPS ====================

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function document()
    {
        return match ($this->document_type) {
            'SALES_INVOICE' => $this->belongsTo(PostedSalesInvoice::class, 'document_id'),
            'SALES_CREDIT_MEMO' => $this->belongsTo(PostedSalesCreditMemo::class, 'document_id'),
            'PURCHASE_INVOICE' => $this->belongsTo(PurchaseInvoice::class, 'document_id'),
            'PURCHASE_CREDIT_MEMO' => $this->belongsTo(PostedPurchaseCreditMemo::class, 'document_id'),
            default => null,
        };
    }

    public function applier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    // ==================== SCOPES ====================

    public function scopeActive($query)
    {
        return $query->where('reversed', false);
    }

    public function scopeForDocument($query, string $type, int $id)
    {
        return $query->where('document_type', $type)->where('document_id', $id);
    }

    // ==================== CALCULATED ATTRIBUTES ====================

    public function getTotalEffectAttribute(): float
    {
        return $this->amount_applied + $this->discount_applied + $this->write_off_amount;
    }

    public function getNetPaymentAmountAttribute(): float
    {
        // Amount applied minus any write-off
        return $this->amount_applied;
    }
}
