<?php
// app/Models/SalesOrder.php

namespace App\Models;

use App\Enums\SalesOrderStatus;
use App\Services\PricingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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
            if ($order->customer_id && !$order->general_business_posting_group_id) {
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
            !in_array($this->status, ['SHIPPED', 'INVOICED', 'CLOSED', 'CANCELLED']);
    }

    public function getCanEditAttribute(): bool
    {
        return in_array($this->status, ['DRAFT', 'PENDING_APPROVAL']);
    }

    public function getCanApproveAttribute(): bool
    {
        return $this->status === 'PENDING_APPROVAL';
    }

    public function getCanReleaseAttribute(): bool
    {
        return $this->status === 'APPROVED';
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

        if ($this->total_amount == 0) return null;

        return (($this->total_amount - $totalCost) / $this->total_amount) * 100;
    }

    // ==================== BUSINESS METHODS ====================

    /**
     * Add a line to the order with automatic pricing
     */
    public function addLine(
        Item $item,
        float $quantity,
        ?string $variantCode = null,
        ?string $uom = null,
        ?\DateTime $requestedDeliveryDate = null
    ): SalesOrderLine {
        $pricingService = new PricingService();

        // Get price
        $priceData = $pricingService->getSalesPrice(
            item: $item,
            customer: $this->customer,
            quantity: $quantity,
            variantCode: $variantCode,
            uom: $uom ?? $item->base_unit_of_measure,
            location: $this->location,
            date: $this->order_date
        );

        $lineNumber = $this->lines()->max('line_number') + 1 ?? 1;

        return $this->lines()->create([
            'line_number' => $lineNumber,
            'item_id' => $item->id,
            'item_code' => $item->item_number,
            'description' => $item->description,
            'variant_code' => $variantCode,
            'general_product_posting_group_id' => $item->general_product_posting_group_id,
            'inventory_posting_group_id' => $item->inventory_posting_group_id,
            'quantity' => $quantity,
            'unit_of_measure_code' => $uom ?? $item->base_unit_of_measure,
            'qty_per_unit_of_measure' => 1, // Would lookup conversion
            'quantity_base' => $quantity,
            'unit_price' => $priceData['unit_price'],
            'unit_cost' => $item->unit_cost,
            'line_discount_percent' => $priceData['discount_percent'],
            'requested_delivery_date' => $requestedDeliveryDate,
            'location_id' => $this->location_id,
            'price_source' => $priceData['price_source'],
            'pricing_master_id' => $priceData['pricing_master_id'],
        ]);
    }

    /**
     * Calculate and update all totals
     */
    public function recalculateTotals(): void
    {
        static::withoutEvents(function () {
            $lines = $this->lines;

            $this->subtotal = $lines->sum('line_total');
            $this->line_discount_total = $lines->sum('line_discount_amount');

            $afterLineDiscounts = $lines->sum('line_amount');

            // Apply invoice discount
            if ($this->invoice_discount_percent) {
                $this->invoice_discount_amount = $afterLineDiscounts * ($this->invoice_discount_percent / 100);
            }

            $this->total_amount = $afterLineDiscounts - $this->invoice_discount_amount;
            $this->total_vat = $lines->sum('vat_amount'); // Simplified - should calc on discounted amount
            $this->grand_total = $this->total_amount + $this->total_vat;

            $this->save();
        });
    }

    /**
     * Create Warehouse Shipment from this order
     */
    public function createWarehouseShipment(?int $userId = null): WarehouseShipment
    {
        if (!$this->can_ship) {
            throw new \Exception('Sales Order cannot be shipped. Status: ' . $this->status);
        }

        return DB::transaction(function () use ($userId) {
            $shipment = WarehouseShipment::create([
                'document_number' => WarehouseShipment::generateNumber(),
                'location_id' => $this->location_id,
                'source_document' => 'SALES_ORDER',
                'source_document_id' => $this->id,
                'source_document_number' => $this->order_number,
                'customer_id' => $this->customer_id,
                'status' => 'OPEN',
                'assigned_user_id' => $userId,
                'shipment_date' => $this->shipment_date ?? now(),
                'planned_delivery_date' => $this->promised_delivery_date,
            ]);

            // Create shipment lines from order lines
            foreach ($this->lines()->where('quantity_to_ship', '>', 0)->get() as $soLine) {
                $shipment->lines()->create([
                    'line_number' => $soLine->line_number,
                    'item_id' => $soLine->item_id,
                    'variant_code' => $soLine->variant_code,
                    'description' => $soLine->description,
                    'quantity' => $soLine->quantity_to_ship,
                    'unit_of_measure_code' => $soLine->unit_of_measure_code,
                    'source_line_id' => $soLine->id,
                ]);
            }

            // Update order status
            $this->status = SalesOrderStatus::PICKING;
            $this->assigned_warehouse_worker_id = $userId;
            $this->save();

            return $shipment;
        });
    }

    /**
     * Post Sales Invoice (creates G/L entries)
     */
    public function postInvoice(
        array $shipmentIds = [], // Specific shipments to invoice, or empty for all
        ?\DateTime $postingDate = null,
        ?string $documentNumber = null
    ): PostedSalesInvoice {
        if (!$this->can_invoice) {
            throw new \Exception('Sales Order cannot be invoiced. Status: ' . $this->status);
        }

        $postingDate = $postingDate ?? now();

        return DB::transaction(function () use ($shipmentIds, $postingDate, $documentNumber) {
            $invoice = PostedSalesInvoice::create([
                'document_number' => $documentNumber ?? PostedSalesInvoice::generateNumber(),
                'order_id' => $this->id,
                'order_number' => $this->order_number,
                'customer_id' => $this->customer_id,
                'customer_name' => $this->customer_name,
                'customer_address' => $this->customer_address,
                'ship_to_name' => $this->ship_to_name,
                'ship_to_address' => $this->ship_to_address,
                'general_business_posting_group_id' => $this->general_business_posting_group_id,
                'customer_posting_group_id' => $this->customer_posting_group_id,
                'vat_bus_posting_group' => $this->vat_bus_posting_group,
                'location_id' => $this->location_id,
                'shipping_agent_code' => $this->shipping_agent_code,
                'posting_date' => $postingDate,
                'document_date' => $postingDate,
                'due_date' => $postingDate->copy()->addDays($this->payment_terms_code ?? 30),
                'shipment_date' => $this->shipment_date,
                'subtotal' => 0,
                'line_discount_total' => 0,
                'invoice_discount_amount' => $this->invoice_discount_amount,
                'total_amount' => 0,
                'total_vat' => 0,
                'grand_total' => 0,
                'currency_code' => $this->currency_code,
                'currency_factor' => $this->currency_factor,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
                'salesperson_id' => $this->salesperson_id,
            ]);

            $postingService = new PostingService();
            $totalSubtotal = 0;
            $totalLineDiscount = 0;
            $totalVat = 0;

            // Get lines to invoice (from shipments or order)
            $linesToInvoice = $this->getLinesToInvoice($shipmentIds);

            foreach ($linesToInvoice as $lineData) {
                $soLine = $lineData['so_line'];
                $quantity = $lineData['quantity'];

                $lineTotal = $quantity * $soLine->unit_price;
                $lineDiscount = $lineTotal * ($soLine->line_discount_percent / 100);
                $lineAmount = $lineTotal - $lineDiscount;
                $vatAmount = $lineAmount * ($soLine->vat_percentage / 100);

                // Calculate COGS
                $unitCost = $soLine->unit_cost ?? $soLine->item->unit_cost;
                $costAmount = $quantity * $unitCost;

                // Create invoice line
                $invLine = $invoice->lines()->create([
                    'so_line_id' => $soLine->id,
                    'so_line_number' => $soLine->line_number,
                    'item_id' => $soLine->item_id,
                    'item_code' => $soLine->item_code,
                    'item_description' => $soLine->description,
                    'variant_code' => $soLine->variant_code,
                    'general_product_posting_group_id' => $soLine->general_product_posting_group_id,
                    'inventory_posting_group_id' => $soLine->inventory_posting_group_id,
                    'quantity' => $quantity,
                    'unit_of_measure_code' => $soLine->unit_of_measure_code,
                    'qty_per_unit_of_measure' => $soLine->qty_per_unit_of_measure,
                    'quantity_base' => $quantity * $soLine->qty_per_unit_of_measure,
                    'unit_price' => $soLine->unit_price,
                    'unit_cost' => $unitCost,
                    'unit_cost_lcy' => $unitCost * $this->currency_factor,
                    'line_discount_percent' => $soLine->line_discount_percent,
                    'line_discount_amount' => $lineDiscount,
                    'line_total' => $lineTotal,
                    'line_amount' => $lineAmount,
                    'vat_code' => $soLine->vat_code,
                    'vat_percentage' => $soLine->vat_percentage,
                    'vat_amount' => $vatAmount,
                    'amount_including_vat' => $lineAmount + $vatAmount,
                    'cost_amount' => $costAmount,
                    'profit_amount' => $lineAmount - $costAmount,
                    'lot_number' => $lineData['lot_number'] ?? null,
                    'serial_number' => $lineData['serial_number'] ?? null,
                    'shipment_id' => $lineData['shipment_id'] ?? null,
                    'line_number' => $soLine->line_number,
                ]);

                // Post G/L entries for this line
                $glAccounts = $postingService->postSalesLine(
                    customer: $this->customer,
                    item: $soLine->item,
                    quantity: $quantity,
                    unitPrice: $soLine->unit_price,
                    lineDiscount: $lineDiscount,
                    lineAmount: $lineAmount,
                    costAmount: $costAmount,
                    postingDate: $postingDate,
                    documentNumber: $invoice->document_number,
                    description: $soLine->description
                );

                // Update invoice line with G/L accounts
                $invLine->update([
                    'sales_account_id' => $glAccounts['sales_account_id'],
                    'cogs_account_id' => $glAccounts['cogs_account_id'],
                    'inventory_account_id' => $glAccounts['inventory_account_id'],
                ]);

                // Update SO line
                $soLine->quantity_invoiced += $quantity;
                $soLine->line_status = $soLine->quantity_invoiced >= $soLine->quantity ? 'INVOICED' : 'PARTIALLY_INVOICED';
                $soLine->save();

                $totalSubtotal += $lineTotal;
                $totalLineDiscount += $lineDiscount;
                $totalVat += $vatAmount;
            }

            // Apply invoice discount proportionally
            if ($this->invoice_discount_amount > 0) {
                $discountRatio = $totalSubtotal > 0 ? $this->invoice_discount_amount / $totalSubtotal : 0;
                $appliedInvoiceDiscount = $totalSubtotal * $discountRatio;
            } else {
                $appliedInvoiceDiscount = 0;
            }

            $totalAmount = $totalSubtotal - $totalLineDiscount - $appliedInvoiceDiscount;

            // Post A/R entry (summary)
            $postingService->postCustomerReceivable(
                customer: $this->customer,
                amount: $totalAmount + $totalVat,
                postingDate: $postingDate,
                documentNumber: $invoice->document_number
            );

            // Update invoice totals
            $invoice->update([
                'subtotal' => $totalSubtotal,
                'line_discount_total' => $totalLineDiscount,
                'invoice_discount_amount' => $appliedInvoiceDiscount,
                'total_amount' => $totalAmount,
                'total_vat' => $totalVat,
                'grand_total' => $totalAmount + $totalVat,
            ]);

            // Update order status
            $this->updateInvoiceStatus();

            return $invoice;
        });
    }

    /**
     * Get lines to invoice based on shipments
     */
    protected function getLinesToInvoice(array $shipmentIds): array
    {
        $lines = [];

        if (empty($shipmentIds)) {
            // Invoice all shipped but uninvoiced quantities
            foreach ($this->lines as $soLine) {
                $qtyToInvoice = $soLine->quantity_shipped - $soLine->quantity_invoiced;
                if ($qtyToInvoice > 0) {
                    $lines[] = [
                        'so_line' => $soLine,
                        'quantity' => $qtyToInvoice,
                        'shipment_id' => null,
                        'lot_number' => null,
                        'serial_number' => null,
                    ];
                }
            }
        } else {
            // Get specific shipment lines
            $shipmentLines = WarehouseShipmentLine::whereIn('warehouse_shipment_id', $shipmentIds)
                ->whereHas('shipment', function ($q) {
                    $q->where('source_document_id', $this->id)
                        ->where('source_document', 'SALES_ORDER');
                })
                ->get();

            foreach ($shipmentLines as $shLine) {
                $soLine = $this->lines()->find($shLine->source_line_id);
                if ($soLine) {
                    $lines[] = [
                        'so_line' => $soLine,
                        'quantity' => $shLine->quantity,
                        'shipment_id' => $shLine->warehouse_shipment_id,
                        'lot_number' => $shLine->lot_number,
                        'serial_number' => $shLine->serial_number,
                    ];
                }
            }
        }

        return $lines;
    }

    /**
     * Update order status based on shipment/invoice progress
     */
    protected function updateInvoiceStatus(): void
    {
        $allLines = $this->lines;
        $fullyInvoiced = $allLines->every(fn($line) => $line->quantity_invoiced >= $line->quantity);
        $partiallyInvoiced = $allLines->some(fn($line) => $line->quantity_invoiced > 0);
        $fullyShipped = $allLines->every(fn($line) => $line->quantity_shipped >= $line->quantity);

        if ($fullyInvoiced) {
            $this->status = SalesOrderStatus::INVOICED;
            $this->fully_invoiced = true;
        } elseif ($partiallyInvoiced) {
            $this->status = SalesOrderStatus::PARTIALLY_INVOICED;
        } elseif ($fullyShipped) {
            $this->status = SalesOrderStatus::SHIPPED;
            $this->fully_shipped = true;
        }

        $this->quantity_invoiced = $allLines->sum('quantity_invoiced');
        $this->save();
    }

    /**
     * Approve the order
     */
    public function approve(int $userId): void
    {
        if (!$this->can_approve) {
            throw new \Exception('Order is not pending approval');
        }

        $this->update([
            'status' => SalesOrderStatus::APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);
    }

    /**
     * Release to warehouse
     */
    public function release(): void
    {
        if ($this->status !== SalesOrderStatus::APPROVED) {
            throw new \Exception('Order must be approved before release');
        }

        $this->update(['status' => SalesOrderStatus::RELEASED]);
    }

    /**
     * Cancel the order
     */
    public function cancel(int $userId, string $reason): void
    {
        if (in_array($this->status, ['SHIPPED', 'INVOICED', 'PARTIALLY_INVOICED'])) {
            throw new \Exception('Cannot cancel shipped or invoiced order');
        }

        $this->update([
            'status' => SalesOrderStatus::CANCELLED,
            'cancelled_by' => $userId,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }

    /**
     * Generate order number
     */
    public static function generateOrderNumber(string $orderType): string
    {
        $prefix = match($orderType) {
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
