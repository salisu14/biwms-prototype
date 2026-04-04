<?php

// app/Models/SalesOrder.php

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
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'dimensions' => 'array',
        'status' => SalesOrderStatus::class,
        'order_type' => SalesOrderType::class,
        'shipping_method' => ShippingMethod::class,
    ];

    protected $attributes = [
        'status' => SalesOrderStatus::DRAFT,
        'order_type' => 'SALES_ORDER',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber($order->order_type);
            }
        });

        // Auto-set posting groups and pricing group from customer
        static::saving(function ($order) {
            if ($order->customer_id && ! $order->general_business_posting_group_id) {
                $customer = Customer::find($order->customer_id);
                if ($customer) {
                    $order->general_business_posting_group_id = $customer->general_business_posting_group_id;
                    $order->customer_posting_group_id = $customer->customer_posting_group_id;
                    $order->vat_bus_posting_group = $customer->vat_bus_posting_group;
                    $order->pricing_group_id = $customer->pricing_group_id;
                    $order->customer_name = $customer->name;
                }
            }
        });
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
        return $this->hasMany(SalesOrderLine::class, 'sales_order_id')
            ->orderBy('line_number');
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

    // Posting Groups
    public function generalBusinessPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralBusinessPostingGroup::class);
    }

    public function customerPostingGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerPostingGroup::class);
    }

    public function pricingGroup(): BelongsTo
    {
        return $this->belongsTo(PricingGroup::class);
    }

    // Warehouse Documents
    public function warehouseShipments(): HasMany
    {
        return $this->hasMany(WarehouseShipment::class, 'source_document_id')
            ->where('source_document', 'SALES_ORDER');
    }

    // Posted Invoices
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
        return $query->where('promised_delivery_date', '<', now())
            ->whereNotIn('status', ['SHIPPED', 'INVOICED', 'CLOSED', 'CANCELLED']);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForSalesperson($query, int $salespersonId)
    {
        return $query->where('salesperson_id', $salespersonId);
    }

    // ==================== CALCULATED ATTRIBUTES ====================

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
        $totalCost = $this->lines->sum(function ($line) {
            return $line->quantity * ($line->unit_cost ?? 0);
        });

        if ($this->total_amount == 0) {
            return null;
        }

        return (($this->total_amount - $totalCost) / $this->total_amount) * 100;
    }

    public static function generateOrderNumber(string $orderType): string
    {
        $prefix = match ($orderType) {
            'RETURN_ORDER' => 'RO',
            'REPLACEMENT' => 'RP',
            'CONTRACT' => 'CT',
            default => 'SO',
        };

        $year = date('Y');
        $count = self::whereYear('created_at', $year)
            ->where('order_type', $orderType)
            ->count() + 1;

        return sprintf('%s-%d-%06d', $prefix, $year, $count);
    }
}
