<?php

namespace App\Models;

use App\Enums\SalesOrderStatus;
use App\Services\NumberSeriesService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class BlanketOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (BlanketOrder $order): void {
            if (empty($order->document_number)) {
                $order->document_number = $order->generateDocumentNumber();
            }
        });
    }

    protected $fillable = [
        'document_number',
        'order_type',
        'external_document_no',
        'vendor_id',
        'customer_id',
        'document_type',
        'status',
        'posting_date',
        'document_date',
        'order_date',
        'starting_date',
        'ending_date',
        'buyer_id',
        'responsibility_center',
        'assigned_user_id',
        'project_code',
        'department_code',
        'shortcut_dimension_1_code',
        'shortcut_dimension_2_code',
        'dimension_set_id',
        'vendor_order_no',
        'purchase_order_no',
        'order_address_code',
        'currency_code',
        'exchange_rate',
        'prices_including_vat',
        'payment_terms_code',
        'payment_method_code',
        'transaction_type',
        'transaction_specification',
        'transport_method',
        'entry_point',
        'area',
        'language_code',
        'format_region',
        'buy_from_vendor_name',
        'buy_from_address',
        'buy_from_address_2',
        'buy_from_city',
        'buy_from_post_code',
        'buy_from_county',
        'buy_from_country_region_code',
        'buy_from_contact',
        'sell_to_customer_no',
        'sell_to_customer_name',
        'sell_to_address',
        'sell_to_address_2',
        'sell_to_city',
        'sell_to_post_code',
        'sell_to_county',
        'sell_to_country_region_code',
        'sell_to_contact',
        'bill_to_customer_no',
        'bill_to_name',
        'bill_to_address',
        'bill_to_address_2',
        'bill_to_city',
        'bill_to_post_code',
        'bill_to_county',
        'bill_to_country_region_code',
        'bill_to_contact',
        'pay_to_vendor_no',
        'pay_to_name',
        'pay_to_address',
        'pay_to_address_2',
        'pay_to_city',
        'pay_to_post_code',
        'pay_to_county',
        'pay_to_country_region_code',
        'pay_to_contact',
        'ship_to_code',
        'ship_to_name',
        'ship_to_address',
        'ship_to_address_2',
        'ship_to_city',
        'ship_to_post_code',
        'ship_to_county',
        'ship_to_country_region_code',
        'ship_to_contact',
        'location_code',
        'shipment_method_code',
        'shipping_agent_code',
        'shipping_agent_service_code',
        'package_tracking_no',
        'invoice_disc_code',
        'requested_receipt_date',
        'promised_receipt_date',
        'quote_no',
        'comment',
        'released',
        'released_at',
        'released_by',
        'created_by',
        'last_modified_by',
        'salesperson_code',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'document_date' => 'date',
        'order_date' => 'date',
        'starting_date' => 'date',
        'ending_date' => 'date',
        'released_at' => 'datetime',
        'requested_receipt_date' => 'date',
        'promised_receipt_date' => 'date',
        'exchange_rate' => 'decimal:6',
        'prices_including_vat' => 'boolean',
        'released' => 'boolean',
    ];

    // Relationships
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BlanketOrderLine::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function releasedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastModifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by');
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_code', 'id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'blanket_order_id');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'blanket_order_id');
    }

    public function vendorInvoices(): HasMany
    {
        return $this->hasMany(VendorInvoice::class, 'source_document_id')
            ->where('source_document_type', 'BLANKET_ORDER');
    }

    public function scopePurchase($query)
    {
        return $query->where('order_type', 'Purchase');
    }

    public function scopeSales($query)
    {
        return $query->where('order_type', 'Sales');
    }

    // Business Logic
    public function release(int $userId): void
    {
        if ($this->released) {
            throw new \Exception('Blanket Order already released');
        }

        $this->update([
            'released' => true,
            'released_at' => now(),
            'released_by' => $userId,
            'status' => 'ACTIVE',
        ]);
    }

    public function reopen(): void
    {
        if (! $this->released) {
            throw new \Exception('Blanket Order is not released');
        }

        $this->update([
            'released' => false,
            'released_at' => null,
            'released_by' => null,
            'status' => 'OPEN',
        ]);
    }

    public function isExpired(): bool
    {
        return $this->ending_date && $this->ending_date < now();
    }

    public function isActive(): bool
    {
        return $this->released &&
            $this->status === 'ACTIVE' &&
            (! $this->ending_date || $this->ending_date >= now());
    }

    public function getTotalAmount(): float
    {
        return $this->lines->sum(function ($line) {
            $price = $this->order_type === 'Sales' ? ($line->unit_price ?? 0) : ($line->direct_unit_cost ?? 0);

            return $line->quantity * $price;
        });
    }

    public function getRemainingAmount(): float
    {
        return $this->lines->sum(function ($line) {
            $fulfilledQty = $this->order_type === 'Sales' ? $line->quantity_shipped : $line->quantity_received;
            $remainingQty = $line->quantity - $fulfilledQty;
            $price = $this->order_type === 'Sales' ? ($line->unit_price ?? 0) : ($line->direct_unit_cost ?? 0);

            return max(0, $remainingQty * $price);
        });
    }

    public function generateDocumentNumber(): string
    {
        $seriesCandidates = $this->order_type === 'Sales'
            ? ['S-BLANKET', 'BLANKET_SALES_ORDER', 'SBO']
            : ['P-BLANKET', 'BLANKET_PURCHASE_ORDER', 'PBO'];

        return app(NumberSeriesService::class)->getNextNoFromSeries($seriesCandidates, null, 'Blanket Order');
    }

    public function createPurchaseOrder(): PurchaseOrder
    {
        if ($this->order_type !== 'Purchase') {
            throw new \Exception('Cannot create Purchase Order from a Sales Blanket Order');
        }
        if (! $this->released) {
            throw new \Exception('Blanket Order must be released before creating Purchase Orders');
        }

        return DB::transaction(function () {
            $purchaseOrder = PurchaseOrder::create([
                'blanket_order_id' => $this->id,
                'vendor_id' => $this->vendor_id,
                'currency_code' => $this->currency_code,
                'payment_terms_code' => $this->payment_terms_code,
                'payment_method_code' => $this->payment_method_code,
                'shortcut_dimension_1_code' => $this->shortcut_dimension_1_code,
                'shortcut_dimension_2_code' => $this->shortcut_dimension_2_code,
                'dimension_set_id' => $this->dimension_set_id,
                'buy_from_vendor_name' => $this->buy_from_vendor_name,
                'buy_from_address' => $this->buy_from_address,
                'buy_from_city' => $this->buy_from_city,
                'buy_from_post_code' => $this->buy_from_post_code,
                'buy_from_country_region_code' => $this->buy_from_country_region_code,
                'ship_to_code' => $this->ship_to_code,
                'ship_to_name' => $this->ship_to_name,
                'ship_to_address' => $this->ship_to_address,
                'location_code' => $this->location_code,
                'status' => 'OPEN',
            ]);

            // Copy lines from blanket order
            foreach ($this->lines as $blanketLine) {
                if ($blanketLine->getRemainingQuantity() > 0) {
                    $purchaseOrder->lines()->create([
                        'blanket_order_line_id' => $blanketLine->id,
                        'type' => $blanketLine->type,
                        'no' => $blanketLine->no,
                        'description' => $blanketLine->description,
                        'description_2' => $blanketLine->description_2,
                        'unit_of_measure' => $blanketLine->unit_of_measure,
                        'quantity' => $blanketLine->getRemainingQuantity(),
                        'direct_unit_cost' => $blanketLine->direct_unit_cost,
                        'line_discount_percent' => $blanketLine->line_discount_percent,
                        'line_discount_amount' => $blanketLine->line_discount_amount,
                        'shortcut_dimension_1_code' => $blanketLine->shortcut_dimension_1_code,
                        'shortcut_dimension_2_code' => $blanketLine->shortcut_dimension_2_code,
                        'dimension_set_id' => $blanketLine->dimension_set_id,
                        'planned_receipt_date' => $blanketLine->planned_receipt_date,
                        'requested_receipt_date' => $blanketLine->requested_receipt_date,
                        'promised_receipt_date' => $blanketLine->promised_receipt_date,
                    ]);
                }
            }

            return $purchaseOrder;
        });
    }

    public function createSalesOrder(): SalesOrder
    {
        if ($this->order_type !== 'Sales') {
            throw new \Exception('Cannot create Sales Order from a Purchase Blanket Order');
        }
        if (! $this->released) {
            throw new \Exception('Blanket Order must be released before creating Sales Orders');
        }

        return DB::transaction(function () {
            $salesOrder = SalesOrder::create([
                'blanket_order_id' => $this->id,
                'customer_id' => $this->customer_id,
                'currency_code' => $this->currency_code,
                'payment_terms_code' => $this->payment_terms_code,
                'payment_method_code' => $this->payment_method_code,
                'shortcut_dimension_1_code' => $this->shortcut_dimension_1_code,
                'shortcut_dimension_2_code' => $this->shortcut_dimension_2_code,
                'dimension_set_id' => $this->dimension_set_id,
                'customer_name' => $this->sell_to_customer_name,
                'customer_address' => $this->sell_to_address,
                'ship_to_name' => $this->ship_to_name,
                'ship_to_address' => $this->ship_to_address,
                'location_id' => Location::where('code', $this->location_code)->first()?->id,
                'status' => SalesOrderStatus::DRAFT,
            ]);

            // Copy lines from blanket order
            foreach ($this->lines as $blanketLine) {
                if ($blanketLine->getRemainingQuantity() > 0) {
                    $salesOrder->lines()->create([
                        'blanket_order_line_id' => $blanketLine->id,
                        'type' => $blanketLine->type,
                        'no' => $blanketLine->no,
                        'description' => $blanketLine->description,
                        'quantity' => $blanketLine->getRemainingQuantity(),
                        'unit_price' => $blanketLine->unit_price,
                        'line_discount_percent' => $blanketLine->line_discount_percent,
                        'line_discount_amount' => $blanketLine->line_discount_amount,
                        'shortcut_dimension_1_code' => $blanketLine->shortcut_dimension_1_code,
                        'shortcut_dimension_2_code' => $blanketLine->shortcut_dimension_2_code,
                        'dimension_set_id' => $blanketLine->dimension_set_id,
                    ]);
                }
            }

            return $salesOrder;
        });
    }
}
