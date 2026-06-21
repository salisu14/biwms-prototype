<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Approvable;
use App\Enums\ItemLedgerEntryType;
use App\Enums\SalesOrderStatus;
use App\Enums\SalesOrderType;
use App\Enums\ShippingMethod;
use App\Services\DimensionManagementService;
use App\Services\NumberSeriesService;
use App\Services\PostingDateValidator;
use App\Services\PostingService;
use App\Traits\Approvable as ApprovableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesOrder extends Model implements Approvable
{
    use ApprovableTrait, HasFactory;

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

            $customerNo = null;
            if ($order->customer_id) {
                $customerNo = Customer::query()->whereKey($order->customer_id)->value('customer_number');
            }

            $dimensions = (array) ($order->dimensions ?? []);
            app(DimensionManagementService::class)->enforceEntityDefaults($dimensions, [
                ['table_id' => '18', 'no' => $customerNo],
            ]);
            $order->dimensions = $dimensions;

            if ($order->relationLoaded('lines')) {
                $order->recalculateTotals();
            }
        });
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
                $this->pricing_group_id = $customer->pricing_group_id;
                $this->is_price_inclusive = $customer->is_price_inclusive;
                $this->customer_name ??= $customer->name;

                if ($customer->vat_bus_posting_group && ! $this->vat_business_posting_group_id) {
                    $this->vat_business_posting_group_id = VatBusinessPostingGroup::query()
                        ->where('code', $customer->vat_bus_posting_group)
                        ->value('id');
                }
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

    public function glEntries(): HasMany
    {
        return $this->hasMany(GlEntry::class, 'source_number', 'order_number')
            ->where('source_type', 'CUSTOMER')
            ->whereIn('source_number', $this->postedInvoices()->select('document_number'));
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
        $seriesCodes = match ($orderType) {
            SalesOrderType::ReturnOrder => ['S-RET', 'SALES_RETURN_ORDER', 'SRO'],
            SalesOrderType::Replacement => ['S-REPL', 'SALES_REPLACEMENT', 'SRP'],
            SalesOrderType::Contract => ['S-CONTRACT', 'SALES_CONTRACT', 'SCT'],
            default => ['S-ORD', 'SALES_ORDER', 'SO'],
        };

        return app(NumberSeriesService::class)->getNextNoFromSeries($seriesCodes, null, 'Sales Order');
    }

    /*
    |--------------------------------------------------------------------------
    | Approvable Interface
    |--------------------------------------------------------------------------
    */

    public function getApprovalAmount(): float
    {
        return (float) ($this->grand_total ?? 0);
    }

    public function getApprovalDocumentType(): string
    {
        return 'Sales Order';
    }

    public function getApprovalRequestorId(): int
    {
        return (int) ($this->created_by ?? auth()->id());
    }

    public function getApprovalPostingGroupId(): ?int
    {
        return $this->customer_posting_group_id;
    }

    public function markAsReleased(): void
    {
        $this->update([
            'status' => SalesOrderStatus::APPROVED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
    }

    /**
     * Post shipment and deduct inventory in base quantity.
     *
     * @throws ValidationException
     */
    public function postShipment(): void
    {
        app(PostingDateValidator::class)->validate($this->posting_date ?? now());
        $this->loadMissing('lines');

        $headerDimensions = app(DimensionManagementService::class)->normalizeDimensionMap((array) ($this->dimensions ?? []));
        app(DimensionManagementService::class)->validateDimensionCombination($headerDimensions);

        if (! in_array($this->status, [SalesOrderStatus::APPROVED, SalesOrderStatus::RELEASED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only approved or released orders can be shipped.',
            ]);
        }

        DB::transaction(function (): void {
            $this->loadMissing('lines.item');

            $shipmentDocumentNumber = $this->getShipmentDocumentNumber();
            $alreadyPosted = ItemLedgerEntry::query()
                ->where('document_number', $shipmentDocumentNumber)
                ->where('entry_type', ItemLedgerEntryType::SALE)
                ->exists();

            if ($alreadyPosted) {
                throw ValidationException::withMessages([
                    'status' => 'Shipment has already been posted for this order.',
                ]);
            }

            $totalShipped = 0.0;

            foreach ($this->lines as $line) {
                $lineDimensions = app(DimensionManagementService::class)->normalizeDimensionMap((array) ($line->dimensions ?? []));
                app(DimensionManagementService::class)->validateDimensionCombination($lineDimensions);

                $remainingToShip = max(0, (float) $line->quantity - (float) $line->quantity_shipped);
                if ($remainingToShip <= 0) {
                    continue;
                }

                $qtyPerUom = (float) ($line->qty_per_unit_of_measure ?: 1);
                $baseQuantityToShip = $remainingToShip * $qtyPerUom;
                $locationId = $line->location_id ?? $this->location_id ?? $line->item?->location_id;

                if (! $locationId) {
                    throw ValidationException::withMessages([
                        'location_id' => "Location is required to post shipment for item {$line->item_code}.",
                    ]);
                }

                ItemLedgerEntry::create([
                    'entry_type' => ItemLedgerEntryType::SALE,
                    'document_type' => 'SALES_ORDER_SHIPMENT',
                    'document_number' => $shipmentDocumentNumber,
                    'document_line_number' => $line->line_number ?? ($line->id * 10),
                    'item_id' => $line->item_id,
                    'variant_code' => $line->variant_code,
                    'location_id' => $locationId,
                    'bin_code' => $line->bin_code,
                    'quantity' => -$baseQuantityToShip,
                    'remaining_quantity' => -$baseQuantityToShip,
                    'serial_number' => $line->serial_number,
                    'lot_number' => $line->lot_number,
                    'expiration_date' => $line->expiration_date,
                    'cost_amount_actual' => (float) ($line->unit_cost ?? 0) * $baseQuantityToShip,
                    'cost_amount_expected' => 0,
                    'purchase_amount_actual' => 0,
                    'source_type' => self::class,
                    'source_id' => $this->id,
                    'general_business_posting_group_id' => $this->general_business_posting_group_id,
                    'general_product_posting_group_id' => $line->general_product_posting_group_id,
                    'inventory_posting_group_id' => $line->inventory_posting_group_id,
                    'dimensions' => $line->dimensions,
                    'posting_date' => $this->posting_date ?? $this->order_date ?? now(),
                    'entry_date' => now(),
                    'open' => false,
                ]);

                if ($line->item) {
                    $line->item->decrement('inventory', $baseQuantityToShip);
                }

                $line->update([
                    'quantity_shipped' => (float) $line->quantity_shipped + $remainingToShip,
                    'quantity_to_ship' => 0,
                    'line_status' => 'SHIPPED',
                ]);

                $totalShipped += $remainingToShip;
            }

            if ($totalShipped <= 0) {
                throw ValidationException::withMessages([
                    'status' => 'No outstanding quantity available to ship.',
                ]);
            }

            $this->refresh()->load('lines');
            $isFullyShipped = $this->lines->every(fn (SalesOrderLine $line): bool => (float) $line->quantity_shipped >= (float) $line->quantity);

            $this->update([
                'quantity_shipped' => (float) $this->lines->sum('quantity_shipped'),
                'fully_shipped' => $isFullyShipped,
                'status' => $isFullyShipped ? SalesOrderStatus::SHIPPED : SalesOrderStatus::RELEASED,
            ]);
        });
    }

    /**
     * Reverse a shipped order back to released state.
     *
     * @throws ValidationException
     */
    public function reverse(): void
    {
        if (! in_array($this->status, [SalesOrderStatus::SHIPPED, SalesOrderStatus::PARTIALLY_INVOICED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only shipped orders can be reversed.',
            ]);
        }

        DB::transaction(function (): void {
            $this->loadMissing(['warehouseShipments.lines', 'lines']);

            $shipmentDocumentNumber = $this->getShipmentDocumentNumber();
            $shipmentLedgerEntries = ItemLedgerEntry::query()
                ->where('document_number', $shipmentDocumentNumber)
                ->where('entry_type', ItemLedgerEntryType::SALE)
                ->get();

            $lineShipmentQtyByLineNumber = [];
            foreach ($shipmentLedgerEntries as $ledgerEntry) {
                $line = $this->lines->firstWhere('line_number', (int) $ledgerEntry->document_line_number);
                if (! $line) {
                    continue;
                }

                $baseQuantity = abs((float) $ledgerEntry->quantity);
                $qtyPerUom = (float) ($line->qty_per_unit_of_measure ?: 1);
                $orderQuantity = $baseQuantity / $qtyPerUom;

                $lineShipmentQtyByLineNumber[$line->line_number] = ($lineShipmentQtyByLineNumber[$line->line_number] ?? 0) + $orderQuantity;
            }

            foreach ($shipmentLedgerEntries as $ledgerEntry) {
                $item = Item::query()->find($ledgerEntry->item_id);
                if ($item) {
                    $item->increment('inventory', abs((float) $ledgerEntry->quantity));
                }
                $ledgerEntry->delete();
            }

            foreach ($this->warehouseShipments as $shipment) {
                foreach ($shipment->lines as $shipmentLine) {
                    if ((float) $shipmentLine->quantity_invoiced > 0) {
                        throw ValidationException::withMessages([
                            'status' => 'Cannot reverse shipped order with invoiced shipment lines.',
                        ]);
                    }

                    if (! $shipmentLine->salesOrderLine) {
                        continue;
                    }

                    $orderLine = $shipmentLine->salesOrderLine;
                    $newQuantityShipped = max(0, (float) $orderLine->quantity_shipped - (float) $shipmentLine->quantity);

                    $orderLine->update([
                        'quantity_shipped' => $newQuantityShipped,
                        'quantity_to_ship' => max(0, (float) $orderLine->quantity - $newQuantityShipped),
                    ]);
                }

                $shipment->lines()->delete();
                $shipment->delete();
            }

            foreach ($this->lines as $line) {
                $shippedByThisPosting = (float) ($lineShipmentQtyByLineNumber[$line->line_number] ?? 0);
                if ($shippedByThisPosting <= 0) {
                    continue;
                }

                $newQuantityShipped = max(0, (float) $line->quantity_shipped - $shippedByThisPosting);
                $line->update([
                    'quantity_shipped' => $newQuantityShipped,
                    'quantity_to_ship' => max(0, (float) $line->quantity - $newQuantityShipped),
                    'line_status' => $newQuantityShipped > 0 ? 'PARTIALLY_SHIPPED' : 'OPEN',
                ]);
            }

            $this->refresh()->load('lines');

            $totalQuantityShipped = (float) $this->lines->sum('quantity_shipped');
            $totalQuantityInvoiced = (float) $this->lines->sum('quantity_invoiced');

            $this->update([
                'quantity_shipped' => $totalQuantityShipped,
                'quantity_invoiced' => $totalQuantityInvoiced,
                'fully_shipped' => false,
                'fully_invoiced' => false,
                'status' => SalesOrderStatus::RELEASED,
            ]);
        });
    }

    private function getShipmentDocumentNumber(): string
    {
        return 'SS-'.$this->order_number;
    }

    /**
     * Post invoice from shipped quantities and create Posted Sales Invoice records.
     * BC-aligned: shipment posting and invoice posting are distinct, but invoice posting
     * produces a posted document and updates invoicing progress.
     *
     * @throws ValidationException
     */
    public function postInvoice(): PostedSalesInvoice
    {
        app(PostingDateValidator::class)->validate($this->posting_date ?? now());
        $this->loadMissing('lines');

        $headerDimensions = app(DimensionManagementService::class)->normalizeDimensionMap((array) ($this->dimensions ?? []));
        app(DimensionManagementService::class)->validateDimensionCombination($headerDimensions);

        if (! in_array($this->status, [SalesOrderStatus::SHIPPED, SalesOrderStatus::PARTIALLY_INVOICED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only shipped orders can be invoiced.',
            ]);
        }

        return DB::transaction(function (): PostedSalesInvoice {
            $this->loadMissing(['lines.item', 'customer']);

            $linesToInvoice = $this->lines->filter(fn (SalesOrderLine $line): bool => (float) $line->quantity_shipped > (float) $line->quantity_invoiced);
            if ($linesToInvoice->isEmpty()) {
                throw ValidationException::withMessages([
                    'status' => 'No shipped quantity is available for invoicing.',
                ]);
            }

            $invoiceNo = app(NumberSeriesService::class)->getNextNoFromSeries(
                ['S-INV', 'SALES_INVOICE', 'SI'],
                null,
                'Posted Sales Invoice'
            );

            $postedInvoice = PostedSalesInvoice::query()->create([
                'document_number' => $invoiceNo,
                'external_document_number' => $this->external_document_number,
                'order_id' => $this->id,
                'order_number' => $this->order_number,
                'customer_id' => $this->customer_id,
                'customer_name' => $this->customer_name,
                'customer_address' => $this->customer_address,
                'ship_to_name' => $this->ship_to_name,
                'ship_to_address' => $this->ship_to_address,
                'general_business_posting_group_id' => $this->general_business_posting_group_id,
                'customer_posting_group_id' => $this->customer_posting_group_id,
                'vat_bus_posting_group' => $this->vatBusinessPostingGroup?->code,
                'location_id' => $this->location_id,
                'shipping_agent_code' => $this->shipping_agent_code,
                'posting_date' => $this->posting_date ?? now()->toDateString(),
                'document_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
                'shipment_date' => $this->shipment_date,
                'currency_code' => $this->currency_code,
                'currency_factor' => $this->currency_factor,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
                'salesperson_id' => $this->salesperson_id,
                'dimensions' => $this->dimensions,
            ]);

            $subtotal = 0.0;
            $lineDiscountTotal = 0.0;
            $totalAmount = 0.0;
            $totalVat = 0.0;

            foreach ($linesToInvoice as $line) {
                $lineDimensions = app(DimensionManagementService::class)->normalizeDimensionMap((array) ($line->dimensions ?? []));
                app(DimensionManagementService::class)->validateDimensionCombination($lineDimensions);

                $quantityToInvoice = max(0, (float) $line->quantity_shipped - (float) $line->quantity_invoiced);
                if ($quantityToInvoice <= 0) {
                    continue;
                }

                $lineTotal = $quantityToInvoice * (float) $line->unit_price;
                $lineDiscountAmount = $lineTotal * ((float) $line->line_discount_percent / 100);
                $lineAmount = $lineTotal - $lineDiscountAmount;
                $vatAmount = $lineAmount * ((float) $line->vat_percentage / 100);
                $costAmount = $quantityToInvoice * (float) ($line->unit_cost ?? 0);

                PostedSalesInvoiceLine::query()->create([
                    'posted_sales_invoice_id' => $postedInvoice->id,
                    'so_line_id' => $line->id,
                    'so_line_number' => $line->line_number,
                    'item_id' => $line->item_id,
                    'item_code' => $line->item_code,
                    'item_description' => $line->description,
                    'variant_code' => $line->variant_code,
                    'posting_date' => $postedInvoice->posting_date,
                    'general_product_posting_group_id' => $line->general_product_posting_group_id,
                    'inventory_posting_group_id' => $line->inventory_posting_group_id,
                    'quantity' => $quantityToInvoice,
                    'unit_of_measure_code' => $line->unit_of_measure_code,
                    'qty_per_unit_of_measure' => $line->qty_per_unit_of_measure,
                    'quantity_base' => $quantityToInvoice * (float) ($line->qty_per_unit_of_measure ?: 1),
                    'unit_price' => $line->unit_price,
                    'unit_cost' => $line->unit_cost,
                    'unit_cost_lcy' => $line->unit_cost,
                    'line_discount_percent' => $line->line_discount_percent,
                    'line_discount_amount' => $lineDiscountAmount,
                    'line_total' => $lineTotal,
                    'line_amount' => $lineAmount,
                    'vat_code' => $line->vat_code,
                    'vat_percentage' => $line->vat_percentage,
                    'vat_amount' => $vatAmount,
                    'amount_including_vat' => $lineAmount + $vatAmount,
                    'cost_amount' => $costAmount,
                    'profit_amount' => $lineAmount - $costAmount,
                    'lot_number' => $line->lot_number,
                    'serial_number' => $line->serial_number,
                    'expiration_date' => $line->expiration_date,
                    'dimensions' => $line->dimensions,
                    'line_number' => $line->line_number,
                ]);

                $line->update([
                    'quantity_invoiced' => (float) $line->quantity_invoiced + $quantityToInvoice,
                    'line_status' => ((float) $line->quantity_invoiced + $quantityToInvoice) >= (float) $line->quantity ? 'INVOICED' : 'PARTIALLY_SHIPPED',
                ]);

                $subtotal += $lineTotal;
                $lineDiscountTotal += $lineDiscountAmount;
                $totalAmount += $lineAmount;
                $totalVat += $vatAmount;
            }

            $invoiceDiscountAmount = $totalAmount * ((float) ($this->invoice_discount_percent ?? 0) / 100);
            $grandTotal = ($totalAmount - $invoiceDiscountAmount) + $totalVat;

            $postedInvoice->update([
                'subtotal' => $subtotal,
                'line_discount_total' => $lineDiscountTotal,
                'invoice_discount_amount' => $invoiceDiscountAmount,
                'total_amount' => $totalAmount,
                'total_vat' => $totalVat,
                'grand_total' => $grandTotal,
                'remaining_amount' => $grandTotal,
            ]);

            $this->postGlEntriesForPostedInvoice($postedInvoice);
            CustomerLedgerEntry::createFromInvoice($postedInvoice);

            $this->refresh()->load('lines');
            $totalQuantityInvoiced = (float) $this->lines->sum('quantity_invoiced');
            $fullyInvoiced = $this->lines->every(fn (SalesOrderLine $line): bool => (float) $line->quantity_invoiced >= (float) $line->quantity);

            $this->update([
                'quantity_invoiced' => $totalQuantityInvoiced,
                'fully_invoiced' => $fullyInvoiced,
                'status' => $fullyInvoiced ? SalesOrderStatus::INVOICED : SalesOrderStatus::PARTIALLY_INVOICED,
            ]);

            $this->refreshLifecycleStatus();

            return $postedInvoice->fresh('lines');
        });
    }

    private function postGlEntriesForPostedInvoice(PostedSalesInvoice $postedInvoice): void
    {
        $postedInvoice->loadMissing(['lines.item', 'customer']);

        $customer = $postedInvoice->customer;
        if (! $customer) {
            throw ValidationException::withMessages([
                'customer_id' => 'Customer is required before posting invoice entries.',
            ]);
        }

        $receivablesAccount = $customer->getReceivablesAccount();
        if (! $receivablesAccount) {
            throw ValidationException::withMessages([
                'customer_posting_group_id' => "Customer '{$customer->name}' is missing receivables account setup.",
            ]);
        }

        $postingService = app(PostingService::class);

        $postingService->createGlEntry([
            'chart_of_account_id' => $receivablesAccount->id,
            'debit_amount' => (float) $postedInvoice->grand_total,
            'credit_amount' => 0,
            'source_type' => 'CUSTOMER',
            'source_number' => $postedInvoice->document_number,
            'document_type' => 'SALES_INVOICE',
            'document_number' => $postedInvoice->document_number,
            'posting_date' => $postedInvoice->posting_date,
            'document_date' => $postedInvoice->document_date,
            'description' => "Invoice {$postedInvoice->document_number}",
        ]);

        foreach ($postedInvoice->lines as $line) {
            $item = $line->item;
            if (! $item) {
                continue;
            }

            $postingSetup = GeneralPostingSetup::query()
                ->where('general_business_posting_group_id', $postedInvoice->general_business_posting_group_id)
                ->where('general_product_posting_group_id', $line->general_product_posting_group_id)
                ->first();

            if (! $postingSetup) {
                throw ValidationException::withMessages([
                    'general_posting_setup' => "Missing posting setup for item {$line->item_code}.",
                ]);
            }

            $salesAccount = $postingSetup->getSalesAccount();
            if (! $salesAccount) {
                throw ValidationException::withMessages([
                    'sales_account' => "Missing sales account for item {$line->item_code}.",
                ]);
            }

            $postingService->createGlEntry([
                'chart_of_account_id' => $salesAccount->id,
                'debit_amount' => 0,
                'credit_amount' => (float) $line->line_amount,
                'source_type' => 'CUSTOMER',
                'source_number' => $postedInvoice->document_number,
                'document_type' => 'SALES_INVOICE',
                'document_number' => $postedInvoice->document_number,
                'posting_date' => $postedInvoice->posting_date,
                'document_date' => $postedInvoice->document_date,
                'description' => "Revenue {$line->item_description}",
            ]);

            if ((float) $line->vat_amount > 0) {
                $vatSetup = VatPostingSetup::query()
                    ->where('vat_bus_posting_group_code', $postedInvoice->vat_bus_posting_group)
                    ->where('vat_prod_posting_group_code', $line->vat_code)
                    ->first();

                if ($vatSetup?->sales_vat_account_id) {
                    $postingService->createGlEntry([
                        'chart_of_account_id' => $vatSetup->sales_vat_account_id,
                        'debit_amount' => 0,
                        'credit_amount' => (float) $line->vat_amount,
                        'source_type' => 'CUSTOMER',
                        'source_number' => $postedInvoice->document_number,
                        'document_type' => 'SALES_INVOICE',
                        'document_number' => $postedInvoice->document_number,
                        'posting_date' => $postedInvoice->posting_date,
                        'document_date' => $postedInvoice->document_date,
                        'description' => "VAT {$line->item_description}",
                    ]);
                }
            }

            if ($item->isInventoryItem() && (float) $line->cost_amount > 0) {
                $cogsAccount = $postingSetup->getCogsAccount();
                $inventoryAccount = $item->getInventoryAccount();

                if (! $cogsAccount || ! $inventoryAccount) {
                    throw ValidationException::withMessages([
                        'inventory_accounts' => "Missing COGS or Inventory account for item {$line->item_code}.",
                    ]);
                }

                $postingService->createGlEntry([
                    'chart_of_account_id' => $cogsAccount->id,
                    'debit_amount' => (float) $line->cost_amount,
                    'credit_amount' => 0,
                    'source_type' => 'CUSTOMER',
                    'source_number' => $postedInvoice->document_number,
                    'document_type' => 'SALES_INVOICE',
                    'document_number' => $postedInvoice->document_number,
                    'posting_date' => $postedInvoice->posting_date,
                    'document_date' => $postedInvoice->document_date,
                    'description' => "COGS {$line->item_description}",
                ]);

                $postingService->createGlEntry([
                    'chart_of_account_id' => $inventoryAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => (float) $line->cost_amount,
                    'source_type' => 'CUSTOMER',
                    'source_number' => $postedInvoice->document_number,
                    'document_type' => 'SALES_INVOICE',
                    'document_number' => $postedInvoice->document_number,
                    'posting_date' => $postedInvoice->posting_date,
                    'document_date' => $postedInvoice->document_date,
                    'description' => "Inventory {$line->item_description}",
                ]);
            }
        }
    }

    public function refreshLifecycleStatus(): void
    {
        // BC-style: payment settlement belongs to customer ledger/invoice lifecycle.
        // Sales order lifecycle is fulfillment-driven and should not auto-close on payment.
    }

    public function isPaidInFull(): bool
    {
        $this->loadMissing('postedInvoices');

        return $this->postedInvoices->isNotEmpty()
            && $this->postedInvoices->every(fn (PostedSalesInvoice $invoice): bool => (float) ($invoice->remaining_amount ?? 0) <= 0.01);
    }

    public function canArchive(): bool
    {
        $this->loadMissing('lines');

        if ($this->status === SalesOrderStatus::CANCELLED) {
            return true;
        }

        return $this->lines->isNotEmpty()
            && (bool) $this->fully_shipped
            && (bool) $this->fully_invoiced;
    }
}
