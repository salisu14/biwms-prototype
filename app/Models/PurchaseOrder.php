<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseOrderType;
use App\Services\Business\BusinessContextService;
use App\Services\Purchase\PurchaseOrderService;
use App\Support\PurchasingCurrency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'business_id',
        'order_number',
        'order_type',
        'vendor_id',
        'vendor_name',
        'order_date',
        'location_id',
        'posting_date',
        'due_date',
        'delivery_date',
        'payment_terms',
        'currency_code',
        'currency_factor',
        'status',
        'comment',
        'total_amount',
        'total_vat',
        'grand_total',
        'total_amount_lcy',
        'total_vat_lcy',
        'grand_total_lcy',
        'created_by',
        'approved_by',
        'approved_at',
        'general_business_posting_group_id',
        'vendor_posting_group_id',
        'vat_bus_posting_group',
        'vat_business_posting_group_id',
        'is_price_inclusive',
    ];

    protected $casts = [
        'order_type' => PurchaseOrderType::class,
        'status' => PurchaseOrderStatus::class,
        'order_date' => 'date',
        'posting_date' => 'date',
        'due_date' => 'date',
        'delivery_date' => 'date',
        'total_amount' => 'decimal:4',
        'total_vat' => 'decimal:4',
        'grand_total' => 'decimal:4',
        'currency_factor' => 'decimal:6',
        'total_amount_lcy' => 'decimal:4',
        'total_vat_lcy' => 'decimal:4',
        'grand_total_lcy' => 'decimal:4',
        'is_price_inclusive' => 'boolean',
        'approved_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => PurchaseOrderStatus::PENDING,
        'order_type' => PurchaseOrderType::PURCHASE_ORDER,
        // Local currency (LCY) is the safe default: NGN documents resolve factor 1.
        // A foreign-currency document must set its currency explicitly and carry a rate.
        'currency_code' => PurchasingCurrency::LCY_CODE,
        'is_price_inclusive' => false,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($order) {
            $order->business_id ??= app(BusinessContextService::class)->resolveId();

            // Default status if not provided
            if (empty($order->status)) {
                $order->status = PurchaseOrderStatus::PENDING;
            }

            if (empty($order->order_number)) {
                $order->order_number = app(PurchaseOrderService::class)
                    ->generateOrderNumber($order->order_type);
            }
        });

        // Auto-set posting groups from vendor as a fallback safeguard
        static::saving(function ($order) {
            if ($order->vendor_id && ! $order->general_business_posting_group_id) {
                $vendor = Vendor::find($order->vendor_id);
                if ($vendor) {
                    $order->general_business_posting_group_id = $vendor->general_business_posting_group_id;
                    $order->vendor_posting_group_id = $vendor->vendor_posting_group_id;
                    $order->vat_bus_posting_group = $vendor->vat_bus_posting_group;
                    $order->is_price_inclusive = $vendor->is_price_inclusive;
                }
            }

            // Changing the document currency or its rate must re-derive the LCY
            // values from the (authoritative) FCY values.
            if ($order->exists
                && $order->isDirty(['currency_factor', 'currency_code'])
                && $order->hasResolvableCurrencyFactor()) {
                $order->syncLineCurrencyValues();
                $order->applyLcyTotalsFromLines();
            }
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

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'purchase_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function generalBusinessPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralBusinessPostingGroup::class);
    }

    public function vendorPostingGroup(): BelongsTo
    {
        return $this->belongsTo(VendorPostingGroup::class);
    }

    public function vatBusinessPostingGroup(): BelongsTo
    {
        return $this->belongsTo(VatBusinessPostingGroup::class);
    }

    public function warehouseReceipts(): HasMany
    {
        return $this->hasMany(WarehouseReceipt::class, 'source_document_id')
            ->where('source_document', 'PURCHASE_ORDER');
    }

    public function postedInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class, 'order_id');
    }

    public function glEntries(): HasMany
    {
        return $this->hasMany(GlEntry::class, 'source_number', 'order_number')
            ->where('source_type', 'VENDOR')
            ->whereIn('source_number', $this->postedInvoices()->select('document_number'));
    }

    // ==================== HELPERS & ACCESORS ====================

    /**
     * Recalculate totals based on current lines.
     * Often used by RelationManagers or Services after line changes.
     */
    public function recalculateTotals(): void
    {
        static::withoutEvents(function () {
            $this->total_amount = $this->lines()->sum('line_total');
            $this->total_vat = $this->lines()->sum('vat_amount');
            $this->grand_total = (float) $this->total_amount + (float) $this->total_vat;

            // Fail closed if a foreign-currency document has no valid rate: the
            // LCY totals are part of the document contract and cannot be guessed.
            $factor = $this->resolvedCurrencyFactor();
            $this->total_amount_lcy = PurchasingCurrency::lcyFromFcy($this->total_amount, $factor);
            $this->total_vat_lcy = PurchasingCurrency::lcyFromFcy($this->total_vat, $factor);
            $this->grand_total_lcy = PurchasingCurrency::lcyFromFcy($this->grand_total, $factor);

            $this->save();
        });
    }

    /**
     * Resolve the document's LCY-per-FCY factor. NGN documents resolve 1;
     * foreign-currency documents require an explicit positive factor.
     *
     * @throws InvalidArgumentException when a foreign-currency document has no valid rate
     */
    public function resolvedCurrencyFactor(): string
    {
        return PurchasingCurrency::factorFor($this->currency_code, $this->currency_factor);
    }

    public function hasResolvableCurrencyFactor(): bool
    {
        try {
            $this->resolvedCurrencyFactor();

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Re-derive each line's LCY values from its FCY values at the current rate.
     */
    public function syncLineCurrencyValues(): void
    {
        $factor = $this->resolvedCurrencyFactor();

        foreach ($this->lines()->get() as $line) {
            $line->forceFill([
                'unit_cost_lcy' => PurchasingCurrency::lcyFromFcy($line->unit_cost, $factor),
                'line_total_lcy' => PurchasingCurrency::lcyFromFcy($line->line_total, $factor),
            ])->saveQuietly();
        }
    }

    private function applyLcyTotalsFromLines(): void
    {
        $factor = $this->resolvedCurrencyFactor();
        $totalAmount = (float) $this->lines()->sum('line_total');
        $totalVat = (float) $this->lines()->sum('vat_amount');

        $this->total_amount_lcy = PurchasingCurrency::lcyFromFcy($totalAmount, $factor);
        $this->total_vat_lcy = PurchasingCurrency::lcyFromFcy($totalVat, $factor);
        $this->grand_total_lcy = PurchasingCurrency::lcyFromFcy($totalAmount + $totalVat, $factor);
    }

    public function getCanEditAttribute(): bool
    {
        return $this->status->canEdit();
    }

    public function getCanReceiveAttribute(): bool
    {
        return $this->status->canReceive();
    }

    // ==================== SCOPES ====================

    public function scopeOfType($query, PurchaseOrderType $type)
    {
        return $query->where('order_type', $type);
    }

    public function scopeWithStatus($query, PurchaseOrderStatus $status)
    {
        return $query->where('status', $status);
    }

    public function refreshLifecycleStatus(): void
    {
        $this->loadMissing('lines');

        $allReceived = $this->lines->isNotEmpty()
            && $this->lines->every(fn (PurchaseOrderLine $line): bool => (float) $line->received_quantity >= (float) $line->quantity);
        $allInvoiced = $this->lines->isNotEmpty()
            && $this->lines->every(fn (PurchaseOrderLine $line): bool => (float) $line->invoiced_quantity >= (float) $line->quantity);

        if ($allReceived && $allInvoiced) {
            $this->update(['status' => PurchaseOrderStatus::CLOSED]);

            return;
        }

        if ($allReceived) {
            $this->update(['status' => PurchaseOrderStatus::RECEIVED]);

            return;
        }

        if ($this->lines->contains(fn (PurchaseOrderLine $line): bool => (float) $line->received_quantity > 0)) {
            $this->update(['status' => PurchaseOrderStatus::PARTIALLY_RECEIVED]);
        }
    }
}
