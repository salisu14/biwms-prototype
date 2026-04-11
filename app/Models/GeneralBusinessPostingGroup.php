<?php

// app/Models/GeneralBusinessPostingGroup.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeneralBusinessPostingGroup extends Model
{
    use HasFactory;

    protected $table = 'general_business_posting_groups';

    protected $fillable = [
        'code',
        'description',
        'default_vat_bus_posting_group',
        'auto_create_vat_bus_posting_group',
        'blocked',
    ];

    protected $casts = [
        'auto_create_vat_bus_posting_group' => 'boolean',
        'blocked' => 'boolean',
    ];

    // Relationships
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    public function generalPostingSetups(): HasMany
    {
        return $this->hasMany(GeneralPostingSetup::class);
    }

    public function itemJournalLines(): HasMany
    {
        return $this->hasMany(ItemJournalLine::class);
    }

    public function itemLedgerEntries(): HasMany
    {
        return $this->hasMany(ItemLedgerEntry::class);
    }

    // Get all product groups this business group is configured with
    public function configuredProductGroups()
    {
        return $this->belongsToMany(
            GeneralProductPostingGroup::class,
            'general_posting_setups',
            'general_business_posting_group_id',
            'general_product_posting_group_id'
        )->withPivot('id', 'blocked');
    }

    // Check if combination exists
    public function hasSetupWith(GeneralProductPostingGroup $productGroup): bool
    {
        return $this->generalPostingSetups()
            ->where('general_product_posting_group_id', $productGroup->id)
            ->exists();
    }

    // Get or create setup with product group
    public function getSetupWith(GeneralProductPostingGroup $productGroup): ?GeneralPostingSetup
    {
        return $this->generalPostingSetups()
            ->where('general_product_posting_group_id', $productGroup->id)
            ->first();
    }

    // Scope
    public function scopeActive($query)
    {
        return $query->where('blocked', false);
    }

    public function scopeCode($query, $code)
    {
        return $query->where('code', $code);
    }
}
