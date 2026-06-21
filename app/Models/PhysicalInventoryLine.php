<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhysicalInventoryLine extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $line): void {
            if (! empty($line->line_no)) {
                return;
            }

            if (empty($line->journal_id)) {
                $line->line_no = 10000;

                return;
            }

            $lastLineNo = self::query()
                ->where('journal_id', $line->journal_id)
                ->max('line_no') ?? 0;

            $line->line_no = $lastLineNo + 10000;
        });
    }

    protected $fillable = [
        'journal_id',
        'line_no',
        'item_id',
        'variant_code',
        'location_code',
        'bin_code',
        'shelf_no',
        'quantity_base',
        'qty_physical_inventory',
        'qty_calculated',
        'unit_of_measure_code',
        'qty_per_unit_of_measure',
        'entry_type',
        'unit_amount',
        'amount',
        'item_description',
        'reason_code',
        'shortcut_dimension_1_code',
        'shortcut_dimension_2_code',
        'dimension_set_id',
        'serial_no',
        'lot_no',
        'expiration_date',
        'phys_invt_counting_period_code',
        'phys_invt_counting_period_type',
        'last_counting_date',
        'next_counting_date',
        'count_frequency_per_year',
        'inventory_posting_group',
        'gen_bus_posting_group',
        'gen_prod_posting_group',
        'use_item_tracking',
        'qty_to_handle',
        'qty_to_invoice',
        'qty_handled',
        'qty_invoiced',
        'no_of_phys_invt_lines',
    ];

    protected $casts = [
        'quantity_base' => 'decimal:4',
        'qty_physical_inventory' => 'decimal:4',
        'qty_calculated' => 'decimal:4',
        'qty_per_unit_of_measure' => 'decimal:4',
        'unit_amount' => 'decimal:4',
        'amount' => 'decimal:4',
        'expiration_date' => 'date',
        'last_counting_date' => 'date',
        'next_counting_date' => 'date',
        'count_frequency_per_year' => 'integer',
        'qty_to_handle' => 'decimal:4',
        'qty_to_invoice' => 'decimal:4',
        'qty_handled' => 'decimal:4',
        'qty_invoiced' => 'decimal:4',
        'use_item_tracking' => 'boolean',
    ];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(PhysicalInventoryJournal::class, 'journal_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

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

    public function inventoryPostingGroup(): BelongsTo
    {
        return $this->belongsTo(InventoryPostingGroup::class, 'inventory_posting_group', 'code');
    }

    public function genBusPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralBusinessPostingGroup::class, 'gen_bus_posting_group', 'code');
    }

    public function genProdPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralProductPostingGroup::class, 'gen_prod_posting_group', 'code');
    }

    public function getQtyDifferenceAttribute(): float
    {
        return (float) ($this->qty_physical_inventory - $this->quantity_base);
    }

    public function getHasDifferenceAttribute(): bool
    {
        return $this->qty_difference !== 0.0;
    }

    /**
     * @return array{
     *     qty_calculated: float,
     *     entry_type: string|null,
     *     qty_to_handle: float,
     *     qty_to_invoice: float,
     *     amount: float
     * }
     */
    public static function calculateCountVariance(
        float $systemQuantity,
        float $physicalQuantity,
        float $unitAmount
    ): array {
        $difference = $physicalQuantity - $systemQuantity;

        return [
            'qty_calculated' => $difference,
            'entry_type' => $difference > 0 ? 'Positive Adjmt.' : ($difference < 0 ? 'Negative Adjmt.' : null),
            'qty_to_handle' => abs($difference),
            'qty_to_invoice' => abs($difference),
            'amount' => abs($difference) * $unitAmount,
        ];
    }
}
