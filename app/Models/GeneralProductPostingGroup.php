<?php

// app/Models/GeneralProductPostingGroup.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeneralProductPostingGroup extends Model
{
    protected $table = 'general_product_posting_groups';

    protected $fillable = [
        'code',
        'description',
        'default_vat_product_posting_group_id', // ✅ FIXED
        'auto_create_vat_prod_posting_group',
        'blocked',
    ];

    protected $casts = [
        'auto_create_vat_prod_posting_group' => 'boolean',
        'blocked' => 'boolean',
    ];

    // ✅ Correct relationship
    public function defaultVatProductPostingGroup()
    {
        return $this->belongsTo(
            VatProductPostingGroup::class,
            'default_vat_product_posting_group_id'
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
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

    public function configuredBusinessGroups()
    {
        return $this->belongsToMany(
            GeneralBusinessPostingGroup::class,
            'general_posting_setups',
            'general_product_posting_group_id',
            'general_business_posting_group_id'
        )->withPivot('id', 'blocked');
    }

    public function hasSetupWith(GeneralBusinessPostingGroup $businessGroup): bool
    {
        return $this->generalPostingSetups()
            ->where('general_business_posting_group_id', $businessGroup->id)
            ->exists();
    }

    public function getSetupWith(GeneralBusinessPostingGroup $businessGroup): ?GeneralPostingSetup
    {
        return $this->generalPostingSetups()
            ->where('general_business_posting_group_id', $businessGroup->id)
            ->first();
    }

    public function getAccountFor(
        GeneralBusinessPostingGroup $businessGroup,
        string $lineType
    ): ?ChartOfAccount {
        $setup = $this->getSetupWith($businessGroup);

        if (! $setup) {
            return null;
        }

        $line = $setup->lines()->where('line_type', $lineType)->first();

        return $line?->chartOfAccount;
    }

    public function scopeActive($query)
    {
        return $query->where('blocked', false);
    }
}
