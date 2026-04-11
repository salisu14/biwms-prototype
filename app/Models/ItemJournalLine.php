<?php

// app/Models/ItemJournalLine.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemJournalLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_journal_batch_id',
        'line_number',
        'entry_type',
        'document_number',
        'posting_date',
        'item_id',
        'variant_code',
        'description',
        'location_id',
        'bin_code',
        'new_location_id',
        'new_bin_code',
        'quantity',
        'unit_of_measure_code',
        'unit_amount',
        'unit_cost',
        'amount',
        'serial_number',
        'lot_number',
        'expiration_date',
        'general_business_posting_group_id',
        'reason_code',
        'posted',
        'posted_date',
        'posted_entry_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_amount' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'amount' => 'decimal:2',
        'posting_date' => 'date',
        'posted_date' => 'datetime',
        'posted' => 'boolean',
    ];

    // Relationships
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemJournalBatch::class, 'item_journal_batch_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function newLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'new_location_id');
    }

    public function generalBusinessPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralBusinessPostingGroup::class);
    }

    // Get product posting group from item
    public function getProductPostingGroup(): GeneralProductPostingGroup
    {
        return $this->item->generalProductPostingGroup;
    }

    // Get posting setup for this line
    public function getPostingSetup(): ?GeneralPostingSetup
    {
        $businessGroupId = $this->general_business_posting_group_id;

        // If no business group (adjustment), use blank/default
        if (! $businessGroupId) {
            $businessGroupId = GeneralBusinessPostingGroup::where('code', '')->first()?->id
                ?? GeneralBusinessPostingGroup::first()?->id;
        }

        return GeneralPostingSetup::where([
            'general_business_posting_group_id' => $businessGroupId,
            'general_product_posting_group_id' => $this->item->general_product_posting_group_id,
        ])->first();
    }

    // Get appropriate account for posting
    public function getPostingAccount(): ?ChartOfAccount
    {
        $setup = $this->getPostingSetup();

        if (! $setup) {
            return null;
        }

        return match ($this->entry_type) {
            'PURCHASE' => $setup->getPurchaseAccount(),
            'SALE' => $setup->getSalesAccount(),
            'POSITIVE_ADJUSTMENT', 'NEGATIVE_ADJUSTMENT' => $setup->getInventoryAdjustmentAccount(),
            default => null,
        };
    }

    // Validate before posting
    public function validateForPosting(): array
    {
        $errors = [];

        if (! $this->item) {
            $errors[] = 'Item not found';
        }

        if ($this->quantity == 0) {
            $errors[] = 'Quantity cannot be zero';
        }

        if (! $this->getPostingSetup()) {
            $errors[] = 'General Posting Setup missing for this combination';
        }

        if (! $this->getPostingAccount()) {
            $errors[] = 'Posting account not configured';
        }

        return $errors;
    }

    // Post this line
    public function post(): bool
    {
        if ($this->posted) {
            return false;
        }

        $errors = $this->validateForPosting();
        if (! empty($errors)) {
            throw new \Exception(implode(', ', $errors));
        }

        // Create item ledger entry
        $entry = ItemLedgerEntry::create([
            'entry_type' => $this->entry_type,
            'document_number' => $this->document_number,
            'item_id' => $this->item_id,
            'location_id' => $this->location_id,
            'bin_code' => $this->bin_code,
            'quantity' => $this->quantity,
            'unit_of_measure_code' => $this->unit_of_measure_code,
            'serial_number' => $this->serial_number,
            'lot_number' => $this->lot_number,
            'expiration_date' => $this->expiration_date,
            'cost_amount_actual' => $this->amount ?? ($this->quantity * $this->unit_cost),
            'general_business_posting_group_id' => $this->general_business_posting_group_id,
            'general_product_posting_group_id' => $this->item->general_product_posting_group_id,
            'inventory_posting_group_id' => $this->item->inventory_posting_group_id,
            'posting_date' => $this->posting_date,
            'entry_date' => now(),
        ]);

        // Update item inventory
        if (in_array($this->entry_type, ['PURCHASE', 'POSITIVE_ADJUSTMENT'])) {
            $this->item->increment('inventory', $this->quantity);
        } elseif (in_array($this->entry_type, ['SALE', 'NEGATIVE_ADJUSTMENT'])) {
            $this->item->decrement('inventory', $this->quantity);
        }

        // Mark as posted
        $this->update([
            'posted' => true,
            'posted_date' => now(),
            'posted_entry_id' => $entry->id,
        ]);

        return true;
    }

    // Scope
    public function scopeUnposted($query)
    {
        return $query->where('posted', false);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('entry_type', $type);
    }
}
