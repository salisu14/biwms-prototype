<?php

// app/Models/ItemLedgerEntry.php

namespace App\Models;

use App\Enums\ItemLedgerEntryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemLedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_number',
        'entry_type',
        'document_type',
        'document_line_number',
        'item_id',
        'variant_code',
        'location_id',
        'bin_code',
        'quantity',
        'remaining_quantity',
        'serial_number',
        'lot_number',
        'expiration_date',
        'cost_amount_actual',
        'cost_amount_expected',
        'purchase_amount_actual',
        'general_business_posting_group_id',
        'general_product_posting_group_id',
        'inventory_posting_group_id',
        'dimensions',
        'posting_date',
        'entry_date',
        'applied_entry_id',
        'open',
        'source_type',
        'source_id',
        'document_number',
        'unit_of_measure_code',
    ];

    protected $casts = [
        'entry_type' => ItemLedgerEntryType::class,
        'quantity' => 'decimal:4',
        'remaining_quantity' => 'decimal:4',
        'cost_amount_actual' => 'decimal:4',
        'cost_amount_expected' => 'decimal:4',
        'purchase_amount_actual' => 'decimal:4',
        'dimensions' => 'array',
        'posting_date' => 'date',
        'entry_date' => 'datetime',
        'open' => 'boolean',
    ];

    // Relationships
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function source()
    {
        return $this->morphTo();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function generalBusinessPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralBusinessPostingGroup::class);
    }

    public function generalProductPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralProductPostingGroup::class);
    }

    public function inventoryPostingGroup(): BelongsTo
    {
        return $this->belongsTo(InventoryPostingGroup::class);
    }

    // Applied entry (for cost application)
    public function appliedEntry(): BelongsTo
    {
        return $this->belongsTo(ItemLedgerEntry::class, 'applied_entry_id');
    }

    // Is positive entry (increase inventory)
    public function isPositiveEntry(): bool
    {
        return in_array($this->entry_type, [
            ItemLedgerEntryType::PURCHASE,
            ItemLedgerEntryType::POSITIVE_ADJUSTMENT,
            ItemLedgerEntryType::TRANSFER, // Depends on location context
            ItemLedgerEntryType::OUTPUT,
            ItemLedgerEntryType::ASSEMBLY_OUTPUT,
        ]);
    }

    // Is negative entry (decrease inventory)
    public function isNegativeEntry(): bool
    {
        return in_array($this->entry_type, [
            ItemLedgerEntryType::SALE,
            ItemLedgerEntryType::NEGATIVE_ADJUSTMENT,
            ItemLedgerEntryType::CONSUMPTION,
            ItemLedgerEntryType::ASSEMBLY_CONSUMPTION,
        ]);
    }

    // Get inventory impact
    public function inventoryImpact(): float
    {
        return $this->isPositiveEntry() ? $this->quantity : -$this->quantity;
    }

    // Close this entry (when fully applied)
    public function close(): void
    {
        $this->update(['open' => false]);
    }

    // Reopen this entry
    public function reopen(): void
    {
        $this->update(['open' => true]);
    }

    // Scope
    public function scopeOpen($query)
    {
        return $query->where('open', true);
    }

    public function scopeForItem($query, int $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    public function scopeForLocation($query, int $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('posting_date', [$startDate, $endDate]);
    }

    protected static function booted(): void
    {
        static::creating(function ($entry) {
            if (! $entry->entry_number) {
                $entry->entry_number = (static::max('entry_number') ?? 0) + 1;
            }
        });
    }
}
