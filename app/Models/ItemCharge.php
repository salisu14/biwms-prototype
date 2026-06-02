<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// #####
//
//

class ItemCharge extends Model
{
    use HasFactory;

    protected $table = 'item_charges';

    protected $fillable = [
        'number',
        'description',
        'description_2',
        'gen_prod_posting_group',
        'vat_prod_posting_group',
        'search_description',
    ];

    protected $casts = [
        // Add specific casts if necessary, e.g., if you add active/sync flags later
    ];

    /**
     * In ERP systems, the business key 'no' is often used for lookups
     * alongside the auto-increment ID. Ensure it's unique in migration.
     */
    public function getRouteKeyName(): string
    {
        return 'number';
    }

    // ─── Relationships ───────────────────────────────────────────

    /**
     * Get the purchase receipt lines where this charge was applied.
     */
    public function purchaseReceiptLines(): HasMany
    {
        return $this->hasMany(PurchaseReceiptLine::class, 'no', 'number')
            ->where('type', 'CHARGE');
    }

    /**
     * Get the purchase order lines where this charge was applied.
     */
    public function purchaseOrderLines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'no', 'number')
            ->where('type', 'CHARGE');
    }

    /**
     * General Product Posting Group
     */
    public function generalPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralProductPostingGroup::class, 'gen_prod_posting_group', 'code');
    }

    /**
     * VAT Product Posting Group
     */
    public function vatPostingGroup(): BelongsTo
    {
        return $this->belongsTo(VatProductPostingGroup::class, 'vat_prod_posting_group', 'code');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopeSearch($query, string $term)
    {
        return $query->where('number', 'like', "%{$term}%")
            ->orWhere('description', 'like', "%{$term}%")
            ->orWhere('search_description', 'like', "%{$term}%");
    }

    // ─── Business Logic ──────────────────────────────────────────

    /**
     * Get the full description combining description and description 2.
     */
    public function getFullDescription(): string
    {
        if ($this->description_2) {
            return "{$this->description} - {$this->description_2}";
        }

        return $this->description ?? '';
    }
}
