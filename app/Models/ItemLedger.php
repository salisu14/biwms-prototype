<?php
// app/Models/ItemLedger.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'item_id',
    'location_id',
    'doc_id',
    'uom_id',
    'created_by',
    'entry_type',
    'quantity',
    'unit_cost',
    'balance_after',
    'cost_after',
    'lot_number',
    'expiry_date'
])]
class ItemLedger extends Model
{
    use HasFactory;

    protected $table = 'item_ledgers';

    // No updated_at (immutable ledger entries)
    const UPDATED_AT = null;

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'balance_after' => 'decimal:4',
        'cost_after' => 'decimal:4',
        'expiry_date' => 'date',
        'created_at' => 'datetime',
    ];

    /**
     * The item moved
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class);
    }

    /**
     * The location
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(LocationMaster::class);
    }

    /**
     * The parent document
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(DocumentHeader::class, 'doc_id', 'id');
    }

    /**
     * Unit of measure for this transaction
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    /**
     * User who created this entry
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /**
     * Calculate cost amount (quantity * unit_cost)
     */
    public function getCostAmountAttribute(): float
    {
        return (float) $this->quantity * (float) $this->unit_cost;
    }

    /**
     * Check if this is an inbound entry (increases inventory)
     */
    public function getIsInboundAttribute(): bool
    {
        return in_array($this->entry_type, [
            'RECEIPT',
            'TRANSFER_IN',
            'RETURN',
            'ADJUSTMENT_POS',
            'PRODUCTION_OUTPUT'
        ]);
    }

    /**
     * Check if this is an outbound entry (decreases inventory)
     */
    public function getIsOutboundAttribute(): bool
    {
        return in_array($this->entry_type, [
            'ISSUE',
            'TRANSFER_OUT',
            'SALE',
            'ADJUSTMENT_NEG',
            'SCRAP'
        ]);
    }

    /**
     * Get signed quantity (+ for inbound, - for outbound)
     */
    public function getSignedQuantityAttribute(): float
    {
        return $this->is_inbound ? (float) $this->quantity : -(float) $this->quantity;
    }

    /**
     * Scope: Inbound entries only
     */
    public function scopeInbound($query)
    {
        return $query->whereIn('entry_type', [
            'RECEIPT', 'TRANSFER_IN', 'RETURN', 'ADJUSTMENT_POS', 'PRODUCTION_OUTPUT'
        ]);
    }

    /**
     * Scope: Outbound entries only
     */
    public function scopeOutbound($query)
    {
        return $query->whereIn('entry_type', [
            'ISSUE', 'TRANSFER_OUT', 'SALE', 'ADJUSTMENT_NEG', 'SCRAP'
        ]);
    }

    /**
     * Scope: For specific item
     */
    public function scopeForItem($query, int $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    /**
     * Scope: For specific location
     */
    public function scopeForLocation($query, int $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    /**
     * Scope: Date range
     */
    public function scopeBetweenDates($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Create ledger entry with automatic balance calculation
     */
    public static function createEntry(array $data): self
    {
        // Calculate running balance before creating
        $currentBalance = self::where('item_id', $data['item_id'])
            ->where('location_id', $data['location_id'])
            ->orderBy('ledger_id', 'desc')
            ->value('balance_after') ?? 0;

        $signedQty = in_array($data['entry_type'], [
            'RECEIPT', 'TRANSFER_IN', 'RETURN', 'ADJUSTMENT_POS', 'PRODUCTION_OUTPUT'
        ]) ? $data['quantity'] : -$data['quantity'];

        $data['balance_after'] = $currentBalance + $signedQty;

        return self::create($data);
    }
}
