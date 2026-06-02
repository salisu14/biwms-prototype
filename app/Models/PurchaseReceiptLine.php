<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReceiptLine extends Model
{
    use HasFactory;

    protected $table = 'purchase_receipt_lines';

    protected $fillable = [
        'purchase_receipt_id',
        'line_number',
        'type',
        'no',
        'description',
        'description_2',
        'unit_of_measure',
        'quantity',
        'quantity_received',
        'quantity_invoiced',
        'direct_unit_cost',
        'unit_cost_lcy',
        'line_amount',
        'line_discount_percent',
        'line_discount_amount',
        'inv_discount_amount',
        'allow_invoice_disc',
        'gross_weight',
        'net_weight',
        'units_per_parcel',
        'unit_volume',
        'appl_to_item_entry',
        'shortcut_dimension_1_code',
        'shortcut_dimension_2_code',
        'dimension_set_id',
        'item_category_code',
        'product_group_code',
        'location_code',
        'bin_code',
        'expected_receipt_date',
        'planned_receipt_date',
        'requested_receipt_date',
        'promised_receipt_date',
        'purchase_order_id',
        'purchase_order_line_id',
        'prod_order_no',
        'prod_order_line_no',
        'job_no',
        'job_task_no',
        'job_line_amount',
        'job_line_amount_lcy',
        'job_currency_code',
        'job_currency_factor',
        'whse_posting_group',
        'variant_code',
        'qty_per_unit_of_measure',
        'unit_of_measure_code',
        'quantity_base',
        'qty_received_base',
        'qty_invoiced_base',
        'item_charge_base_amount',
        'correction',
        'cross_reference_no',
        'cross_reference_type',
        'cross_reference_type_no',
        'transaction_type',
        'transport_method',
        'attached_to_line_no',
        'entry_point',
        'area',
        'transaction_specification',
        'tax_area_code',
        'tax_liable',
        'tax_group_code',
        'use_tax',
        'vat_bus_posting_group',
        'vat_prod_posting_group',
        'vat_base_amount',
        'system_created_entry',
        'vat_difference',
        'inv_disc_amount_to_invoice',
        'prepayment_percent',
        'prepmt_line_amount',
        'prepmt_amt_inv',
        'prepmt_amt_incl_vat',
        'prepayment_vat_difference',
        'prepayment_vat_diff_to_deduct',
        'prepayment_vat_diff_deducted',
        'qty_to_receive',
        'qty_to_invoice',
        'qty_to_assign',
        'qty_assigned',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'quantity_received' => 'decimal:4',
        'quantity_invoiced' => 'decimal:4',
        'direct_unit_cost' => 'decimal:4',
        'unit_cost_lcy' => 'decimal:4',
        'line_amount' => 'decimal:4',
        'line_discount_percent' => 'decimal:2',
        'line_discount_amount' => 'decimal:4',
        'inv_discount_amount' => 'decimal:4',
        'allow_invoice_disc' => 'boolean',
        'gross_weight' => 'decimal:4',
        'net_weight' => 'decimal:4',
        'units_per_parcel' => 'decimal:4',
        'unit_volume' => 'decimal:4',
        'appl_to_item_entry' => 'integer',
        'job_line_amount' => 'decimal:4',
        'job_line_amount_lcy' => 'decimal:4',
        'job_currency_factor' => 'decimal:6',
        'qty_per_unit_of_measure' => 'decimal:4',
        'quantity_base' => 'decimal:4',
        'qty_received_base' => 'decimal:4',
        'qty_invoiced_base' => 'decimal:4',
        'item_charge_base_amount' => 'decimal:4',
        'correction' => 'boolean',
        'tax_liable' => 'boolean',
        'use_tax' => 'decimal:4',
        'vat_base_amount' => 'decimal:4',
        'system_created_entry' => 'decimal:4',
        'vat_difference' => 'decimal:4',
        'inv_disc_amount_to_invoice' => 'decimal:4',
        'prepayment_percent' => 'decimal:2',
        'prepmt_line_amount' => 'decimal:4',
        'prepmt_amt_inv' => 'decimal:4',
        'prepmt_amt_incl_vat' => 'decimal:4',
        'prepayment_vat_difference' => 'decimal:4',
        'prepayment_vat_diff_to_deduct' => 'decimal:4',
        'prepayment_vat_diff_deducted' => 'decimal:4',
        'qty_to_receive' => 'decimal:4',
        'qty_to_invoice' => 'decimal:4',
        'qty_to_assign' => 'decimal:4',
        'qty_assigned' => 'decimal:4',
        'expected_receipt_date' => 'date',
        'planned_receipt_date' => 'date',
        'requested_receipt_date' => 'date',
        'promised_receipt_date' => 'date',
    ];

    // Relationships
    public function purchaseReceipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'no', 'item_code');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_code', 'code');
    }

    public function vendorInvoiceLines(): HasMany
    {
        return $this->hasMany(VendorInvoiceLine::class, 'receipt_line_id');
    }

    // Scopes
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeItemLines($query)
    {
        return $query->where('type', 'ITEM');
    }

    public function scopeFullyReceived($query)
    {
        return $query->whereColumn('quantity_received', '>=', 'quantity');
    }

    public function scopePartiallyReceived($query)
    {
        return $query->whereColumn('quantity_received', '>', 0)
            ->whereColumn('quantity_received', '<', 'quantity');
    }

    public function scopeNotReceived($query)
    {
        return $query->where('quantity_received', 0);
    }

    public function scopeFullyInvoiced($query)
    {
        return $query->whereColumn('quantity_invoiced', '>=', 'quantity');
    }

    // Business Logic
    public function getRemainingQuantity(): float
    {
        return max(0, $this->quantity - $this->quantity_received);
    }

    public function getRemainingQuantityToInvoice(): float
    {
        return max(0, $this->quantity - $this->quantity_invoiced);
    }

    public function getOutstandingAmount(): float
    {
        return $this->getRemainingQuantity() * $this->direct_unit_cost;
    }

    public function isFullyReceived(): bool
    {
        return $this->quantity_received >= $this->quantity;
    }

    public function isPartiallyReceived(): bool
    {
        return $this->quantity_received > 0 && $this->quantity_received < $this->quantity;
    }

    public function isFullyInvoiced(): bool
    {
        return $this->quantity_invoiced >= $this->quantity;
    }

    public function calculateLineAmount(): float
    {
        $discountAmount = $this->line_discount_amount ??
            ($this->direct_unit_cost * $this->quantity * $this->line_discount_percent / 100);

        return ($this->direct_unit_cost * $this->quantity) - $discountAmount;
    }

    public function updateReceivedQuantity(float $quantity): void
    {
        $this->increment('quantity_received', $quantity);
        $this->increment('qty_received_base', $quantity * ($this->qty_per_unit_of_measure ?? 1));
    }

    public function updateInvoicedQuantity(float $quantity): void
    {
        $this->increment('quantity_invoiced', $quantity);
        $this->increment('qty_invoiced_base', $quantity * ($this->qty_per_unit_of_measure ?? 1));
    }

    /**
     * Get the related vendor invoice line if exists
     */
    public function getVendorInvoiceLine(): ?VendorInvoiceLine
    {
        return $this->vendorInvoiceLines()->first();
    }
}
