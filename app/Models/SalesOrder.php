<?php

namespace App\Models;

use App\Enums\SalesOrderStatus;
use App\Enums\SalesOrderType;
use App\Enums\ShippingMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'external_document_number',
        'order_type',
        'customer_id',
        'customer_name',
        'customer_address',
        'ship_to_name',
        'ship_to_address',
        'general_business_posting_group_id',
        'customer_posting_group_id',
        'vat_business_posting_group_id',
        'vat_bus_posting_group',
        'pricing_group_id',
        'location_id',
        'shipping_agent_code',
        'shipping_agent_service_code',
        'shipping_method',
        'order_date',
        'posting_date',
        'requested_delivery_date',
        'promised_delivery_date',
        'shipment_date',
        'payment_terms_code',
        'payment_method_code',
        'subtotal',
        'line_discount_total',
        'invoice_discount_percent',
        'invoice_discount_amount',
        'total_amount',
        'total_vat',
        'grand_total',
        'currency_code',
        'currency_factor',
        'status',
        'quantity_shipped',
        'quantity_invoiced',
        'fully_shipped',
        'fully_invoiced',
        'salesperson_id',
        'assigned_warehouse_worker_id',
        'approved_by',
        'approved_at',
        'created_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'dimensions',
        'internal_comment',
        'customer_comment',
        'is_price_inclusive',
    ];

    protected $casts = [
        'order_date' => 'date',
        'posting_date' => 'date',
        'requested_delivery_date' => 'date',
        'promised_delivery_date' => 'date',
        'shipment_date' => 'date',
        'subtotal' => 'decimal:4',
        'line_discount_total' => 'decimal:4',
        'invoice_discount_percent' => 'decimal:2',
        'invoice_discount_amount' => 'decimal:4',
        'total_amount' => 'decimal:4',
        'total_vat' => 'decimal:4',
        'grand_total' => 'decimal:4',
        'currency_factor' => 'decimal:6',
        'quantity_shipped' => 'decimal:4',
        'quantity_invoiced' => 'decimal:4',
        'fully_shipped' => 'boolean',
        'fully_invoiced' => 'boolean',
        'is_price_inclusive' => 'boolean',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'dimensions' => 'array',
        'status' => SalesOrderStatus::class,
        'order_type' => SalesOrderType::class,
        'shipping_method' => ShippingMethod::class,
    ];

    protected $attributes = [
        'order_type' => SalesOrderType::class,
        'status' => SalesOrderStatus::DRAFT,
        'currency_factor' => 1,
        'subtotal' => 0,
        'grand_total' => 0,
        'is_price_inclusive' => false,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (SalesOrder $order) {
            // Generate order number if missing
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber($order->order_type);
            }

            // Set creator automatically
            $order->created_by = auth()->id();

            // Ensure totals are set
            $order->subtotal ??= 0;
            $order->grand_total ??= 0;
            $order->currency_factor ??= 1;
        });

        static::saving(function (SalesOrder $order) {
            $order->populateCustomerPostingGroups();
            if ($order->relationLoaded('lines')) {
                $order->recalculateTotals();
            }
        });
    }

    /**
     * Submit order for approval
     */
    public function submitForApproval(): void
    {
        if ($this->status !== SalesOrderStatus::DRAFT) {
            throw new \Exception('Only draft orders can be submitted for approval.');
        }

        $this->status = SalesOrderStatus::PENDING_APPROVAL;
        $this->save();
    }

    /**
     * Approve the order
     */
    public function approve(int $userId): void
    {
        if ($this->status !== SalesOrderStatus::PENDING_APPROVAL) {
            throw new \Exception('Only orders pending approval can be approved.');
        }

        $this->status = SalesOrderStatus::APPROVED;
        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->save();
    }

    /**
     * Reject or Return to Draft
     */
    public function returnToDraft(): void
    {
        $this->status = SalesOrderStatus::DRAFT;
        $this->save();
    }

    public function isPosted(): bool
    {
        return in_array($this->status, [
            SalesOrderStatus::SHIPPED,
            SalesOrderStatus::INVOICED,
            SalesOrderStatus::CLOSED,
            SalesOrderStatus::CANCELLED,
        ]);
    }

    /**
     * Populate posting groups and default customer info
     */
    public function populateCustomerPostingGroups(): void
    {
        if ($this->customer_id && ! $this->general_business_posting_group_id) {
            $customer = Customer::find($this->customer_id);
            if ($customer) {
                $this->general_business_posting_group_id = $customer->general_business_posting_group_id;
                $this->customer_posting_group_id = $customer->customer_posting_group_id;
                $this->vat_bus_posting_group = $customer->vat_bus_posting_group;
                $this->pricing_group_id = $customer->pricing_group_id;
                $this->is_price_inclusive = $customer->is_price_inclusive;
                $this->customer_name ??= $customer->name;
            }
        }
    }

    /**
     * Recalculate order totals from lines
     */
    public function recalculateTotals(): void
    {
        $this->subtotal = $this->lines->sum('line_total');
        $this->line_discount_total = $this->lines->sum('line_discount_amount');
        $this->total_amount = $this->lines->sum('line_amount');
        $this->total_vat = $this->lines->sum('vat_amount');

        $this->invoice_discount_amount = $this->total_amount * ($this->invoice_discount_percent / 100);
        $this->grand_total = ($this->total_amount - $this->invoice_discount_amount) + $this->total_vat;
    }

    // ==================== RELATIONSHIPS ====================
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class, 'sales_order_id')->orderBy('line_number');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function warehouseWorker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_warehouse_worker_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
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

    public function vatBusinessPostingGroup(): BelongsTo
    {
        return $this->belongsTo(VatBusinessPostingGroup::class);
    }

    public function pricingGroup(): BelongsTo
    {
        return $this->belongsTo(PricingGroup::class);
    }

    public function warehouseShipments(): HasMany
    {
        return $this->hasMany(WarehouseShipment::class, 'source_document_id')->where('source_document', 'SALES_ORDER');
    }

    public function postedInvoices(): HasMany
    {
        return $this->hasMany(PostedSalesInvoice::class, 'order_id');
    }

    // ==================== SCOPES ====================
    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', ['CLOSED', 'CANCELLED', 'FULLY_INVOICED']);
    }

    public function scopeReadyToShip($query)
    {
        return $query->whereIn('status', ['APPROVED', 'RELEASED', 'PARTIALLY_SHIPPED']);
    }

    public function scopeOverdue($query)
    {
        return $query->where('promised_delivery_date', '<', now())->whereNotIn('status', ['SHIPPED', 'INVOICED', 'CLOSED', 'CANCELLED']);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForSalesperson($query, int $salespersonId)
    {
        return $query->where('salesperson_id', $salespersonId);
    }

    // ==================== ATTRIBUTES ====================
    public function getIsOverdueAttribute(): bool
    {
        return $this->promised_delivery_date &&
            $this->promised_delivery_date < now() &&
            ! in_array($this->status, ['SHIPPED', 'INVOICED', 'CLOSED', 'CANCELLED']);
    }

    public function getCanEditAttribute(): bool
    {
        return in_array($this->status, ['DRAFT', 'PENDING_APPROVAL']);
    }

    public function getCanApproveAttribute(): bool
    {
        return $this->status === SalesOrderStatus::PENDING_APPROVAL;
    }

    public function getCanReleaseAttribute(): bool
    {
        return $this->status === SalesOrderStatus::APPROVED;
    }

    public function getCanShipAttribute(): bool
    {
        return in_array($this->status, ['APPROVED', 'RELEASED', 'PARTIALLY_SHIPPED']);
    }

    public function getCanInvoiceAttribute(): bool
    {
        return in_array($this->status, ['SHIPPED', 'PARTIALLY_INVOICED']);
    }

    public function getTotalQuantityAttribute(): float
    {
        return $this->lines->sum('quantity');
    }

    public function getTotalQuantityShippedAttribute(): float
    {
        return $this->lines->sum('quantity_shipped');
    }

    public function getTotalQuantityToShipAttribute(): float
    {
        return $this->lines->sum('quantity_to_ship');
    }

    public function getProfitMarginPercentAttribute(): ?float
    {
        $totalCost = $this->lines->sum(fn ($line) => $line->quantity * ($line->unit_cost ?? 0));

        return $this->total_amount ? (($this->total_amount - $totalCost) / $this->total_amount) * 100 : null;
    }

    public static function generateOrderNumber(SalesOrderType $orderType): string
    {
        $prefix = match ($orderType) {
            SalesOrderType::ReturnOrder => 'RO',
            SalesOrderType::Replacement => 'RP',
            SalesOrderType::Contract => 'CT',
            default => 'SO',
        };

        $year = date('Y');
        $count = self::whereYear('created_at', $year)
            ->where('order_type', $orderType)
            ->count() + 1;

        return sprintf('%s-%d-%06d', $prefix, $year, $count);
    }
}
