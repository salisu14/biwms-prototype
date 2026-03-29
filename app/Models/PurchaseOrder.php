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
use Illuminate\Support\Facades\DB;

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
    'approved_at',
    // New posting-related fields
    'general_business_posting_group_id',
    'vendor_posting_group_id',
    'vat_bus_posting_group',
])]
class PurchaseOrder extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $table = 'purchase_orders';

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
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber($order->order_type);
            }
            if (empty($order->status)) {
                $order->status = PurchaseOrderStatus::PENDING;
            }
        });

        // Auto-set posting groups from vendor
        static::saving(function ($order) {
            if ($order->vendor_id && !$order->general_business_posting_group_id) {
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

    // NEW: Posting Group Relationships
    public function generalBusinessPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralBusinessPostingGroup::class);
    }

    public function vendorPostingGroup(): BelongsTo
    {
        return $this->belongsTo(VendorPostingGroup::class);
    }

    // NEW: Warehouse Receipts created from this PO
    public function warehouseReceipts(): HasMany
    {
        return $this->hasMany(WarehouseReceipt::class, 'source_document_id')
            ->where('source_document', 'PURCHASE_ORDER');
    }

    // NEW: Posted Purchase Invoices
    public function postedInvoices(): HasMany
    {
        return $this->hasMany(PostedPurchaseInvoice::class, 'order_id');
    }

    // ==================== POSTING WORKFLOW METHODS ====================

    /**
     * Create Warehouse Receipt from this PO
     */
    public function createWarehouseReceipt(?int $userId = null): WarehouseReceipt
    {
        if (!$this->canReceive()) {
            throw new \Exception('Purchase Order cannot be received. Status: ' . $this->status->value);
        }

        return DB::transaction(function () use ($userId) {
            $receipt = WarehouseReceipt::create([
                'document_number' => WarehouseReceipt::generateNumber(),
                'location_id' => $this->location_id,
                'source_document' => 'PURCHASE_ORDER',
                'source_document_id' => $this->id,
                'source_document_number' => $this->order_number,
                'vendor_id' => $this->vendor_id,
                'status' => 'OPEN',
                'assigned_user_id' => $userId,
                'receipt_date' => now(),
                'expected_receipt_date' => $this->delivery_date,
            ]);

            // Create receipt lines from PO lines
            foreach ($this->lines()->where('remaining_quantity', '>', 0)->get() as $poLine) {
                $receipt->lines()->create([
                    'line_number' => $poLine->line_number,
                    'item_id' => $poLine->item_id,
                    'variant_code' => $poLine->variant_code,
                    'description' => $poLine->description,
                    'quantity' => $poLine->remaining_quantity,
                    'unit_of_measure_code' => $poLine->unit_of_measure,
                    'source_line_id' => $poLine->id,
                ]);
            }

            // Update PO status
            $this->status = PurchaseOrderStatus::RECEIVING;
            $this->save();

            return $receipt;
        });
    }

    /**
     * Post Purchase Invoice (creates G/L entries)
     */
    public function postInvoice(
        array $invoiceLines, // ['po_line_id' => 1, 'quantity' => 10, ...]
        \DateTime $postingDate,
        ?string $documentNumber = null
    ): PostedPurchaseInvoice {
        return DB::transaction(function () use ($invoiceLines, $postingDate, $documentNumber) {
            $invoice = PostedPurchaseInvoice::create([
                'document_number' => $documentNumber ?? PostedPurchaseInvoice::generateNumber(),
                'order_id' => $this->id,
                'vendor_id' => $this->vendor_id,
                'posting_date' => $postingDate,
                'due_date' => $postingDate->copy()->addDays($this->payment_terms ?? 30),
                'general_business_posting_group_id' => $this->general_business_posting_group_id,
                'vendor_posting_group_id' => $this->vendor_posting_group_id,
                'vat_bus_posting_group' => $this->vat_bus_posting_group,
                'total_amount' => 0,
                'total_vat' => 0,
                'grand_total' => 0,
            ]);

            $postingService = new PostingService();
            $totalAmount = 0;
            $totalVat = 0;

            foreach ($invoiceLines as $lineData) {
                $poLine = $this->lines()->find($lineData['po_line_id']);
                if (!$poLine) continue;

                $quantity = $lineData['quantity'];
                $lineTotal = $quantity * $poLine->unit_cost;
                $vatAmount = $lineTotal * ($poLine->vat_percentage / 100);

                // Create invoice line
                $invLine = $invoice->lines()->create([
                    'line_number' => $poLine->line_number,
                    'po_line_id' => $poLine->id,
                    'item_id' => $poLine->item_id,
                    'description' => $poLine->description,
                    'quantity' => $quantity,
                    'unit_of_measure' => $poLine->unit_of_measure,
                    'unit_cost' => $poLine->unit_cost,
                    'line_total' => $lineTotal,
                    'vat_percentage' => $poLine->vat_percentage,
                    'vat_amount' => $vatAmount,
                    'total_amount' => $lineTotal + $vatAmount,
                ]);

                // Post G/L entries for this line
                $postingService->postPurchaseLine(
                    vendor: $this->vendor,
                    item: $poLine->item,
                    quantity: $quantity,
                    unitCost: $poLine->unit_cost,
                    lineTotal: $lineTotal,
                    postingDate: $postingDate,
                    documentNumber: $invoice->document_number,
                    description: $poLine->description
                );

                // Update PO line
                $poLine->invoiced_quantity += $quantity;
                $poLine->save();

                $totalAmount += $lineTotal;
                $totalVat += $vatAmount;
            }

            // Post A/P entry (summary)
            $postingService->postVendorPayable(
                vendor: $this->vendor,
                amount: $totalAmount + $totalVat,
                postingDate: $postingDate,
                documentNumber: $invoice->document_number
            );

            // Update invoice totals
            $invoice->update([
                'total_amount' => $totalAmount,
                'total_vat' => $totalVat,
                'grand_total' => $totalAmount + $totalVat,
            ]);

            // Update PO status if fully invoiced
            $this->updateInvoiceStatus();

            return $invoice;
        });
    }

    /**
     * Update PO status based on invoice/receipt progress
     */
    protected function updateInvoiceStatus(): void
    {
        $allLines = $this->lines;
        $fullyInvoiced = $allLines->every(fn($line) => $line->is_fully_invoiced);
        $partiallyInvoiced = $allLines->some(fn($line) => $line->invoiced_quantity > 0);

        if ($fullyInvoiced) {
            $this->status = PurchaseOrderStatus::INVOICED;
        } elseif ($partiallyInvoiced) {
            $this->status = PurchaseOrderStatus::PARTIALLY_INVOICED;
        }

        $this->save();
    }

    // ==================== EXISTING METHODS ====================

    public static function generateOrderNumber(PurchaseOrderType $orderType): ?string
    {
        $series = NumberSeries::where('code', $orderType->seriesCode())
            ->where('is_active', true)
            ->first();

        if (!$series) {
            $year = date('Y');
            $count = self::whereYear('created_at', $year)
                    ->where('order_type', $orderType)
                    ->count() + 1;
            return sprintf('%d-%s-%05d', $year, $orderType->code(), $count);
        }

        $series->checkYearReset();
        return $series->generateNumber();
    }

    public function recalculateTotals(): void
    {
        static::withoutEvents(function () {
            $this->total_amount = $this->lines()->sum('line_total');
            $this->total_vat = $this->lines()->sum('vat_amount');
            $this->grand_total = $this->total_amount + $this->total_vat;
            $this->save();
        });
    }

    public function scopeOfType($query, PurchaseOrderType $type)
    {
        return $query->where('order_type', $type);
    }

    public function scopeWithStatus($query, PurchaseOrderStatus $status)
    {
        return $query->where('status', $status);
    }

    public function getCanEditAttribute(): bool
    {
        return $this->status->canEdit();
    }

    public function getCanReceiveAttribute(): bool
    {
        return $this->status->canReceive();
    }

    public function getOrderTypeLabelAttribute(): string
    {
        return $this->order_type->label();
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    public function getStatusColorAttribute(): string
    {
        return $this->status->color();
    }

    public function getStatusIconAttribute(): string
    {
        return $this->status->icon();
    }

    public function approve(int $userId): void
    {
        $this->status = PurchaseOrderStatus::APPROVED;
        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->save();
    }

    public function cancel(): void
    {
        $this->status = PurchaseOrderStatus::CANCELLED;
        $this->save();
    }

    public function close(): void
    {
        $this->status = PurchaseOrderStatus::CLOSED;
        $this->save();
    }
}
