<?php

namespace App\Models;

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
            if (!$line->quantity_base) {
                $line->quantity_base = $line->quantity;
            }
            $basePrice = $line->unit_amount ?? $line->unit_cost ?? 0;
            $line->amount = ($line->quantity * $basePrice) - ($line->discount_amount ?? 0);
        });
    }

    // ==================== RELATIONSHIPS ====================

    // ❌ REMOVED: template() relationship. Lines belong to a Batch, not directly to a Template.

    public function batch(): BelongsTo { return $this->belongsTo(ItemJournalBatch::class, 'batch_id'); }
    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function zone(): BelongsTo { return $this->belongsTo(Zone::class); }
    public function bin(): BelongsTo { return $this->belongsTo(Bin::class); }
    public function newLocation(): BelongsTo { return $this->belongsTo(Location::class, 'new_location_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    // ==================== POSTING LOGIC ====================

    /**
     * @throws \Throwable
     */
    public function post(): bool
    {
        if ($this->posted) return false;

        return DB::transaction(function () {
            $ledgerEntry = ItemLedgerEntry::create([
                'entry_type' => $this->entry_type,
                'entry_number' => $this->document_no,
                'item_id' => $this->item_id,
                'variant_code' => $this->variant_code,
                'location_id' => $this->location_id,
                'bin_code' => $this->bin?->bin_code,
                'quantity' => $this->quantity_base,
                'remaining_quantity' => $this->quantity_base,
                'lot_number' => $this->lot_number,
                'serial_number' => $this->serial_number,
                'expiration_date' => $this->expiration_date,
                'cost_amount_actual' => $this->amount_lcy ?? $this->amount,
                'posting_date' => $this->posting_date,
                'entry_date' => now(),
                'open' => true,
                'general_business_posting_group_id' => $this->gen_bus_posting_group_id,
                'inventory_posting_group_id' => $this->inventory_posting_group_id,
                'dimensions' => $this->dimension_set_entry,
            ]);

            // ✅ FIX: Use quantity_base here too!
            $this->adjustStock($this->item, (float) $this->quantity_base, $this->entry_type);

            $this->update([
                'posted' => true,
                'posted_at' => now(),
                'item_ledger_entry_id' => $ledgerEntry->id,
            ]);

            return true;
        });
    }

    protected function adjustStock(Item $item, float $qty, string $type): void
    {
        $isPositive = in_array($type, [
            'positive_adjustment', 'purchase', 'output', 'prod_output', 'assembly_output'
        ]);

        $isNegative = in_array($type, [
            'negative_adjustment', 'sale', 'consumption', 'prod_consumption', 'assembly_consumption'
        ]);

        if ($isPositive) {
            $item->increment('inventory', $qty);
        } elseif ($isNegative) {
            $item->decrement('inventory', $qty);
        }
    }

    public function calculateUnitCost()
    {
        $item = $this->item;
        return match($item->costing_method) {
            'FIFO' => $item->getFIFOCost($this->quantity),
            'Average' => $item->average_cost,
            'Standard' => $item->standard_cost,
            'LIFO' => $item->getLIFOCost($this->quantity),
            default => $item->last_direct_cost,
        };
    }

    /**
     * @throws \Throwable
     */
    public function postToLedgerAndGL(): void
    {
        DB::transaction(function() {
            $itemLedgerEntry = ItemLedgerEntry::create([
                'item_id' => $this->item_id,
                'entry_type' => $this->entry_type,
                'quantity' => $this->quantity_base, // ✅ FIX: Use base
                'remaining_quantity' => $this->quantity_base, // ✅ FIX: Use base
                'cost_amount_actual' => $this->unit_amount,
                'purchase_amount_actual' => $this->amount, // ✅ FIX: Correct column name
                'location_id' => $this->location_id,
                'bin_code' => $this->bin?->bin_code, // ✅ FIX: Use relationship
                'posting_date' => $this->posting_date, // ✅ FIX: Was $this->journalLine->...
                'document_number' => $this->document_no, // ✅ FIX: Was document_line_number
                'serial_number' => $this->serial_number, // ✅ FIX: Was serial_no
                'lot_number' => $this->lot_number, // ✅ FIX: Was lot_no
            ]);

            $this->update(['item_ledger_entry_id' => $itemLedgerEntry->id]);

            if ($this->item) {
                $this->item->recalculateInventory();
            }

            $this->postToGeneralLedger($itemLedgerEntry);
        });
    }

    protected function postToGeneralLedger($itemLedgerEntry): void
    {
        // ✅ FIX: Correct variable name (_id was missing)
        $postingGroup = InventoryPostingGroup::find($this->inventory_posting_group_id);
        if (!$postingGroup) return;

        // ✅ FIX: Match lowercase enum values exactly
        $accounts = match($this->entry_type) {
            'purchase', 'positive_adjustment' => [
                'debit' => $postingGroup->inventory_account,
                'credit' => $postingGroup->direct_cost_applied_account,
            ],
            'sale', 'negative_adjustment' => [
                'debit' => $postingGroup->inventory_adjmt_account,
                'credit' => $postingGroup->inventory_account,
            ],
            'transfer' => null,
            default => method_exists($postingGroup, 'getDefaultAccounts') ? $postingGroup->getDefaultAccounts() : null,
        };

        if ($accounts) {
            // Create G/L entries logic here
        }
    }
}
