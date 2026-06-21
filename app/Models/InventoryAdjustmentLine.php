<?php

// app/Models/InventoryAdjustmentLine.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustmentLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_id',
        'line_no',
        'item_id',
        'variant_code',
        'location_code',
        'bin_code',
        'quantity',
        'unit_of_measure_code',
        'unit_cost',
        'amount',
        'quantity_base',
        'qty_per_unit_of_measure',
        'entry_type', // Positive Adjmt., Negative Adjmt.
        'reason_code',
        'description',
        'shortcut_dimension_1_code',
        'shortcut_dimension_2_code',
        'dimension_set_id',
        'applies_to_entry',
        'serial_no',
        'lot_no',
        'expiration_date',
        'line_amount',
        'line_discount_amount',
        'inventory_posting_group',
        'gen_bus_posting_group',
        'gen_prod_posting_group',
        'quantity_to_handle',
        'quantity_to_invoice',
        'qty_handled',
        'qty_invoiced',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'amount' => 'decimal:4',
        'quantity_base' => 'decimal:4',
        'qty_per_unit_of_measure' => 'decimal:4',
        'expiration_date' => 'date',
        'line_amount' => 'decimal:4',
        'line_discount_amount' => 'decimal:4',
        'quantity_to_handle' => 'decimal:4',
        'quantity_to_invoice' => 'decimal:4',
        'qty_handled' => 'decimal:4',
        'qty_invoiced' => 'decimal:4',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_code', 'code');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'bin_code', 'bin_code');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_code', 'uom_code');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustmentJournal::class, 'journal_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function inventoryPostingGroup(): BelongsTo
    {
        return $this->belongsTo(InventoryPostingGroup::class, 'inventory_posting_group', 'code');
    }

    public function generalBusinessPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralBusinessPostingGroup::class, 'gen_bus_posting_group', 'code');
    }

    public function generalProductPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralProductPostingGroup::class, 'gen_prod_posting_group', 'code');
    }

    public function reasonCode(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'reason_code', 'code');
    }

    public function getIsPositiveAdjustmentAttribute(): bool
    {
        return $this->entry_type === 'Positive Adjmt.';
    }

    public function getRemainingQtyAttribute(): float
    {
        return $this->quantity_to_handle - $this->qty_handled;
    }

    /**
     * @return array{
     *     quantity_base: float,
     *     line_amount: float,
     *     amount: float,
     *     quantity_to_handle: float,
     *     quantity_to_invoice: float
     * }
     */
    public static function calculateAmounts(
        float $quantity,
        float $qtyPerUnitOfMeasure,
        float $unitCost,
        float $lineDiscountAmount
    ): array {
        $lineAmount = $quantity * $unitCost;

        return [
            'quantity_base' => $quantity * $qtyPerUnitOfMeasure,
            'line_amount' => $lineAmount,
            'amount' => $lineAmount - $lineDiscountAmount,
            'quantity_to_handle' => $quantity,
            'quantity_to_invoice' => $quantity,
        ];
    }
}
