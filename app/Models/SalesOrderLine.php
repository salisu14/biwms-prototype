<?php

// app/Models/SalesOrderLine.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_id',
        'line_number',
        'item_id',
        'item_code',
        'description',
        'description_2',
        'variant_code',
        'general_product_posting_group_id',
        'inventory_posting_group_id',
        'quantity',
        'quantity_shipped',
        'quantity_invoiced',
        'quantity_to_ship',
        'unit_of_measure_code',
        'qty_per_unit_of_measure',
        'quantity_base',
        'unit_price',
        'unit_cost',
        'line_discount_percent',
        'line_discount_amount',
        'line_total',
        'line_amount',
        'vat_code',
        'vat_percentage',
        'vat_amount',
        'amount_including_vat',
        'planned_delivery_date',
        'requested_delivery_date',
        'promised_delivery_date',
        'reserved_quantity',
        'reservation_entry_id',
        'lot_number',
        'serial_number',
        'expiration_date',
        'location_id',
        'bin_code',
        'line_status',
        'return_against_line_id',
        'return_quantity',
        'dimensions',
        'comment',
        'price_source',
        'pricing_master_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'quantity_shipped' => 'decimal:4',
        'quantity_invoiced' => 'decimal:4',
        'quantity_to_ship' => 'decimal:4',
        'qty_per_unit_of_measure' => 'decimal:4',
        'quantity_base' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'line_discount_percent' => 'decimal:2',
        'line_discount_amount' => 'decimal:4',
        'line_total' => 'decimal:4',
        'line_amount' => 'decimal:4',
        'vat_percentage' => 'decimal:2',
        'vat_amount' => 'decimal:4',
        'amount_including_vat' => 'decimal:4',
        'reserved_quantity' => 'decimal:4',
        'return_quantity' => 'decimal:4',
        'planned_delivery_date' => 'date',
        'requested_delivery_date' => 'date',
        'promised_delivery_date' => 'date',
        'expiration_date' => 'date',
        'dimensions' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($line) {
            $line->syncUnitPriceForSelectedUomOnCreate();

            // Auto-calculate line number if not set
            if (empty($line->line_number)) {
                $maxLineNumber = self::where('sales_order_id', $line->sales_order_id)->max('line_number');
                $line->line_number = ($maxLineNumber ?? 0) + 10;
            }

            // Calculation safety
            $line->qty_per_unit_of_measure = (float) ($line->qty_per_unit_of_measure ?: 1.0);
            $line->quantity_base = (float) $line->quantity * $line->qty_per_unit_of_measure;
            $line->vat_percentage = (float) ($line->vat_percentage ?: 0);

            // Calculate derived fields
            $line->line_total = (float) $line->quantity * (float) $line->unit_price;
            $line->line_discount_amount = $line->line_total * ($line->line_discount_percent / 100);
            $line->line_amount = $line->line_total - $line->line_discount_amount;
            $line->vat_amount = $line->line_amount * ($line->vat_percentage / 100);
            $line->amount_including_vat = $line->line_amount + $line->vat_amount;
            $line->quantity_to_ship = $line->quantity;

            // Copy posting groups from item if not set
            if ($line->item_id && ! $line->general_product_posting_group_id) {
                $item = Item::find($line->item_id);
                if ($item) {
                    $line->general_product_posting_group_id = $item->general_product_posting_group_id;
                    $line->inventory_posting_group_id = $item->inventory_posting_group_id;
                    $line->unit_cost = $item->unit_cost;
                }
            }
        });

        static::updating(function ($line) {
            $line->syncUnitPriceForSelectedUomOnUpdate();

            // Recalculate if price or quantity changed
            if ($line->isDirty(['quantity', 'unit_price', 'line_discount_percent', 'vat_percentage', 'qty_per_unit_of_measure'])) {
                $line->qty_per_unit_of_measure = (float) ($line->qty_per_unit_of_measure ?: 1.0);
                $line->quantity_base = (float) $line->quantity * $line->qty_per_unit_of_measure;

                $line->line_total = (float) $line->quantity * (float) $line->unit_price;
                $line->line_discount_amount = $line->line_total * ($line->line_discount_percent / 100);
                $line->line_amount = $line->line_total - $line->line_discount_amount;
                $line->vat_amount = $line->line_amount * ($line->vat_percentage / 100);
                $line->amount_including_vat = $line->line_amount + $line->vat_amount;
            }

            // Update quantity to ship
            $line->quantity_to_ship = $line->quantity - $line->quantity_shipped;
        });
    }

    protected function syncUnitPriceForSelectedUomOnCreate(): void
    {
        if (! $this->item_id) {
            return;
        }

        $item = Item::find($this->item_id);
        if (! $item) {
            return;
        }

        $baseUnitPrice = (float) ($item->unit_price ?? 0);
        $conversionFactor = (float) ($this->qty_per_unit_of_measure ?: $item->getConversionFactorForUom($this->unit_of_measure_code));
        $conversionFactor = $conversionFactor > 0 ? $conversionFactor : 1.0;
        $expectedUnitPriceForUom = $baseUnitPrice * $conversionFactor;
        $enteredUnitPrice = (float) ($this->unit_price ?? 0);

        if ($enteredUnitPrice === 0.0) {
            $this->unit_price = $expectedUnitPriceForUom;

            return;
        }

        // If UI/API passed base-unit price while UOM factor > 1, auto-correct to UOM price.
        if ($conversionFactor > 1 && abs($enteredUnitPrice - $baseUnitPrice) < 0.0001) {
            $this->unit_price = $expectedUnitPriceForUom;
        }
    }

    protected function syncUnitPriceForSelectedUomOnUpdate(): void
    {
        if (! $this->item_id || ! $this->isDirty(['unit_of_measure_code', 'qty_per_unit_of_measure'])) {
            return;
        }

        $item = Item::find($this->item_id);
        if (! $item) {
            return;
        }

        $baseUnitPrice = (float) ($item->unit_price ?? 0);
        $previousFactor = (float) ($this->getOriginal('qty_per_unit_of_measure') ?: 1.0);
        $previousExpectedPrice = $baseUnitPrice * $previousFactor;
        $previousUnitPrice = (float) $this->getOriginal('unit_price');
        $wasManualPrice = abs($previousUnitPrice - $previousExpectedPrice) > 0.0001;

        if ($wasManualPrice || $this->isDirty('unit_price')) {
            return;
        }

        $newFactor = (float) ($this->qty_per_unit_of_measure ?: $item->getConversionFactorForUom($this->unit_of_measure_code));
        $newFactor = $newFactor > 0 ? $newFactor : 1.0;
        $this->unit_price = $baseUnitPrice * $newFactor;
    }

    // ==================== RELATIONSHIPS ====================

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function generalProductPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralProductPostingGroup::class);
    }

    public function inventoryPostingGroup(): BelongsTo
    {
        return $this->belongsTo(InventoryPostingGroup::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function pricingMaster(): BelongsTo
    {
        return $this->belongsTo(PricingMaster::class);
    }

    public function warehouseShipmentLines(): HasMany
    {
        return $this->hasMany(WarehouseShipmentLine::class, 'source_line_id');
    }

    public function postedInvoiceLines(): HasMany
    {
        return $this->hasMany(PostedSalesInvoiceLine::class, 'so_line_id');
    }

    public function returnAgainstLine(): BelongsTo
    {
        return $this->belongsTo(self::class, 'return_against_line_id');
    }

    public function returnLines(): HasMany
    {
        return $this->hasMany(self::class, 'return_against_line_id');
    }

    // ==================== CALCULATED ATTRIBUTES ====================

    public function getRemainingQuantityAttribute(): float
    {
        return max(0, $this->quantity - $this->quantity_shipped);
    }

    public function getRemainingToInvoiceAttribute(): float
    {
        return max(0, $this->quantity_shipped - $this->quantity_invoiced);
    }

    public function getIsFullyShippedAttribute(): bool
    {
        return $this->quantity_shipped >= $this->quantity;
    }

    public function getIsFullyInvoicedAttribute(): bool
    {
        return $this->quantity_invoiced >= $this->quantity;
    }

    public function getIsPartiallyShippedAttribute(): bool
    {
        return $this->quantity_shipped > 0 && $this->quantity_shipped < $this->quantity;
    }

    public function getProfitAmountAttribute(): float
    {
        $cost = $this->unit_cost * $this->quantity;

        return $this->line_amount - $cost;
    }

    public function getProfitPercentAttribute(): float
    {
        if ($this->line_amount == 0) {
            return 0;
        }

        return ($this->profit_amount / $this->line_amount) * 100;
    }

    // ==================== POSTING HELPERS ====================

    /**
     * Get General Posting Setup for this line
     */
    public function getPostingSetup(): ?GeneralPostingSetup
    {
        $businessGroupId = $this->salesOrder->general_business_posting_group_id;
        $productGroupId = $this->general_product_posting_group_id;

        if (! $businessGroupId || ! $productGroupId) {
            return null;
        }

        return GeneralPostingSetup::where([
            'general_business_posting_group_id' => $businessGroupId,
            'general_product_posting_group_id' => $productGroupId,
        ])->first();
    }

    /**
     * Get sales account for posting
     */
    public function getSalesAccount(): ?ChartOfAccount
    {
        return $this->getPostingSetup()?->getSalesAccount();
    }

    /**
     * Get COGS account for posting
     */
    public function getCogsAccount(): ?ChartOfAccount
    {
        return $this->getPostingSetup()?->getCogsAccount();
    }

    /**
     * Validate posting setup exists
     */
    public function validatePostingSetup(): array
    {
        $errors = [];

        if (! $this->getPostingSetup()) {
            $errors[] = 'General Posting Setup missing for Business Group: '.
                $this->salesOrder->generalBusinessPostingGroup?->code.
                ' and Product Group: '.
                $this->generalProductPostingGroup?->code;
        }

        if (! $this->getSalesAccount()) {
            $errors[] = 'Sales Account not configured';
        }

        if (! $this->getCogsAccount()) {
            $errors[] = 'COGS Account not configured';
        }

        return $errors;
    }

    // ==================== BUSINESS METHODS ====================

    /**
     * Reserve inventory for this line
     */
    public function reserve(float $quantity): bool
    {
        if ($quantity > $this->remaining_quantity) {
            return false;
        }

        // Create reservation entry
        // Implementation depends on your inventory reservation system

        $this->reserved_quantity += $quantity;
        $this->save();

        return true;
    }

    /**
     * Cancel reservation
     */
    public function cancelReservation(float $quantity): void
    {
        $this->reserved_quantity = max(0, $this->reserved_quantity - $quantity);
        $this->save();
    }
}
