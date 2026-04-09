<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseOrderType;
use App\Services\Purchase\PurchaseOrderService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $table = 'purchase_orders';

    protected $fillable = [
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
        'status',
        'comment',
        'total_amount',
        'total_vat',
        'grand_total',
        'created_by',
        'approved_by',
        'approved_at',
        'general_business_posting_group_id',
        'vendor_posting_group_id',
        'vat_bus_posting_group',
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
        'approved_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => PurchaseOrderStatus::PENDING,
        'order_type' => PurchaseOrderType::PURCHASE_ORDER,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($order) {
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
                }
            }
        });
    }

    // ==================== RELATIONSHIPS ====================

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
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

    public function warehouseReceipts(): HasMany
    {
        return $this->hasMany(WarehouseReceipt::class, 'source_document_id')
            ->where('source_document', 'PURCHASE_ORDER');
    }

    public function postedInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class, 'order_id');
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
            $this->save();
        });
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
}
