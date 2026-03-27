<?php
// app/Models/PurchaseOrder.php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseOrderType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
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
    'approved_at'
])]
class PurchaseOrder extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $table = 'purchase_orders';

    protected $casts = [
        'order_type' => PurchaseOrderType::class,  // Enum cast
        'status' => PurchaseOrderStatus::class,      // Enum cast
        'order_date' => 'date',
        'posting_date' => 'date',
        'due_date' => 'date',
        'delivery_date' => 'date',
        'total_amount' => 'decimal:4',
        'total_vat' => 'decimal:4',
        'grand_total' => 'decimal:4',
        'approved_at' => 'datetime',
    ];

    /**
     * Default values
     */
    protected $attributes = [
        'status' => PurchaseOrderStatus::PENDING,
        'order_type' => PurchaseOrderType::PURCHASE_ORDER,
    ];

    /**
     * Auto-generate order number on create
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber($order->order_type);
            }

            // Set default status if not provided
            if (empty($order->status)) {
                $order->status = PurchaseOrderStatus::PENDING;
            }
        });
    }

    /**
     * Generate order number from series using enum
     */
    public static function generateOrderNumber(PurchaseOrderType $orderType): ?string
    {
        $series = NumberSeries::where('code', $orderType->seriesCode())
            ->where('is_active', true)
            ->first();

        if (!$series) {
            // Fallback using enum code
            $year = date('Y');
            $count = self::whereYear('created_at', $year)
                    ->where('order_type', $orderType)
                    ->count() + 1;
            return sprintf('%d-%s-%05d', $year, $orderType->code(), $count);
        }

        $series->checkYearReset();
        return $series->generateNumber();
    }

    /**
     * Vendor relationship
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Location/Warehouse
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(LocationMaster::class, 'location_id');
    }

    /**
     * Order lines/items
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'purchase_order_id');
    }

    /**
     * Creator
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Approver
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Calculate totals from lines
     */
    public function recalculateTotals(): void
    {
        $this->total_amount = $this->lines()->sum('line_total');
        $this->total_vat = $this->lines()->sum('vat_amount');
        $this->grand_total = $this->total_amount + $this->total_vat;
        $this->save();
    }

    /**
     * Scope by order type enum
     */
    public function scopeOfType($query, PurchaseOrderType $type)
    {
        return $query->where('order_type', $type);
    }

    /**
     * Scope by status enum
     */
    public function scopeWithStatus($query, PurchaseOrderStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Check if can be edited using enum method
     */
    public function getCanEditAttribute(): bool
    {
        return $this->status->canEdit();
    }

    /**
     * Check if can receive using enum method
     */
    public function getCanReceiveAttribute(): bool
    {
        return $this->status->canReceive();
    }

    /**
     * Get order type label (delegates to enum)
     */
    public function getOrderTypeLabelAttribute(): string
    {
        return $this->order_type->label();
    }

    /**
     * Get status label (delegates to enum)
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return $this->status->color();
    }

    /**
     * Get status icon for UI
     */
    public function getStatusIconAttribute(): string
    {
        return $this->status->icon();
    }

    /**
     * Approve the order
     */
    public function approve(int $userId): void
    {
        $this->status = PurchaseOrderStatus::APPROVED;
        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->save();
    }

    /**
     * Cancel the order
     */
    public function cancel(): void
    {
        $this->status = PurchaseOrderStatus::CANCELLED;
        $this->save();
    }

    /**
     * Close the order
     */
    public function close(): void
    {
        $this->status = PurchaseOrderStatus::CLOSED;
        $this->save();
    }
}
