<?php

namespace App\Models;

use App\Enums\ItemLedgerEntryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ItemJournalLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'line_no',
        'posting_date',
        'entry_type',
        'document_no',
        'external_document_no',
        'item_id',
        'variant_code',
        'description',
        'unit_of_measure_code',
        'quantity',
        'quantity_base',
        'location_id',
        'zone_id',
        'bin_id',
        'new_location_id',
        'new_zone_id',
        'new_bin_id',
        'lot_number',
        'serial_number',
        'expiration_date',
        'warranty_date',
        'unit_amount',
        'unit_cost',
        'amount',
        'discount_amount',
        'currency_code',
        'amount_lcy',
        'gen_bus_posting_group_id',
        'inventory_posting_group_id',
        'dimension_set_entry',
        'source_code',
        'reason_code',
        'posted',
        'posted_at',
        'item_ledger_entry_id',
        'created_by'
    ];

    protected $casts = [
        'posting_date' => 'date',
        'quantity' => 'decimal:4',
        'quantity_base' => 'decimal:4',
        'unit_amount' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'amount' => 'decimal:4',
        'amount_lcy' => 'decimal:4',
        'posted' => 'boolean',
        'posted_at' => 'datetime',
        'dimension_set_entry' => 'json',
    ];

    protected static function booted(): void
    {
        static::saving(function (ItemJournalLine $line) {
            // Auto-calculate Base Quantity if not provided
            if (!$line->quantity_base) {
                $line->quantity_base = $line->quantity;
            }
            // Auto-calculate Line Amount based on quantity and cost/price
            $basePrice = $line->unit_amount ?? $line->unit_cost ?? 0;
            $line->amount = ($line->quantity * $basePrice) - ($line->discount_amount ?? 0);
        });
    }

    // ==================== RELATIONSHIPS ====================

    public function batch(): BelongsTo { return $this->belongsTo(ItemJournalBatch::class, 'batch_id'); }
    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function zone(): BelongsTo { return $this->belongsTo(Zone::class); }
    public function bin(): BelongsTo { return $this->belongsTo(Bin::class); }
    public function newLocation(): BelongsTo { return $this->belongsTo(Location::class, 'new_location_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    // ==================== POSTING LOGIC ====================

    /**
     * Post the current line to the Item Ledger
     */
    public function post(): bool
    {
        if ($this->posted) return false;

        return DB::transaction(function () {
            // 1. Create Ledger Entry using the provided ItemLedgerEntry model structure
            $ledgerEntry = ItemLedgerEntry::create([
                'entry_type' => $this->entry_type,
                'document_no' => $this->document_no,
                'item_id' => $this->item_id,
                'variant_code' => $this->variant_code,
                'location_id' => $this->location_id,
                'bin_code' => $this->bin?->bin_code, // Map ID to Code for Ledger consistency
                'quantity' => $this->quantity,
                'remaining_quantity' => $this->quantity, // Initialized as full quantity for new entries
                'unit_of_measure_code' => $this->unit_of_measure_code,
                'lot_number' => $this->lot_number,
                'serial_number' => $this->serial_number,
                'expiration_date' => $this->expiration_date,
                'cost_amount_actual' => $this->amount_lcy ?? $this->amount,
                'posting_date' => $this->posting_date,
                'entry_date' => now(),
                'open' => true, // Entry is open until fully applied
                'general_business_posting_group_id' => $this->gen_bus_posting_group_id,
                'inventory_posting_group_id' => $this->inventory_posting_group_id,
                'dimensions' => $this->dimension_set_entry,
                'created_by' => auth()->id() ?? $this->created_by,
            ]);

            // 2. Handle Stock Adjustments
            $this->adjustStock($this->item, (float) $this->quantity, $this->entry_type);

            // 3. Mark journal line as posted
            $this->update([
                'posted' => true,
                'posted_at' => now(),
                'item_ledger_entry_id' => $ledgerEntry->id,
            ]);

            return true;
        });
    }

    /**
     * Adjust physical inventory levels based on entry type
     */
    protected function adjustStock(Item $item, float $qty, string $type): void
    {
        // Define impacts using types consistent with the Ledger model's logic
        $isPositive = in_array($type, [
            'positive_adj',
            'purchase',
            'output',
            'assembly_output'
        ]);

        $isNegative = in_array($type, [
            'negative_adj',
            'sale',
            'consumption',
            'assembly_consumption'
        ]);

        if ($isPositive) {
            $item->increment('inventory', $qty);
        }

        if ($isNegative) {
            $item->decrement('inventory', $qty);
        }

        // Transfer logic handles source decrement and destination increment
        if ($type === 'transfer') {
            // In a basic setup, total global inventory might not change,
            // but location-specific tracking (if implemented) would be updated here.
        }
    }
}
