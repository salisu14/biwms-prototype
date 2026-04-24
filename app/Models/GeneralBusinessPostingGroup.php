<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneralBusinessPostingGroup extends Model
{
    use HasFactory;

    protected $table = 'general_business_posting_groups';

    protected $fillable = [
        'code',
        'description',
        'default_vat_business_posting_group_id', // ✅ FIXED
        'auto_create_vat_bus_posting_group',
        'blocked',
    ];

    protected $casts = [
        'auto_create_vat_bus_posting_group' => 'boolean',
        'blocked' => 'boolean',
    ];

    /**
     * ✅ FIXED: Link to VAT Business Posting Group
     */
    public function defaultVatBusinessPostingGroup(): BelongsTo
    {
        return $this->belongsTo(
            VatBusinessPostingGroup::class,
            'default_vat_business_posting_group_id'
        );
    }

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

    /**
     * Product group mapping (correct)
     */
    public function configuredProductGroups()
    {
        return $this->belongsToMany(
            GeneralProductPostingGroup::class,
            'general_posting_setups',
            'general_business_posting_group_id',
            'general_product_posting_group_id'
        )->withPivot('id', 'blocked');
    }

    public function hasSetupWith(GeneralProductPostingGroup $productGroup): bool
    {
        return $this->generalPostingSetups()
            ->where('general_product_posting_group_id', $productGroup->id)
            ->exists();
    }

    public function getSetupWith(GeneralProductPostingGroup $productGroup): ?GeneralPostingSetup
    {
        return $this->generalPostingSetups()
            ->where('general_product_posting_group_id', $productGroup->id)
            ->first();
    }

    public function scopeActive($query)
    {
        return $query->where('blocked', false);
    }

    public function scopeCode($query, $code)
    {
        return $query->where('code', $code);
    }

    private function getBusinessPostingGroups(): array
    {
        $codes = ['DOMESTIC', 'EXPORT', 'FOREIGN', 'MANUFACTURING'];

        $groups = [];

        foreach ($codes as $code) {
            $group = GeneralBusinessPostingGroup::firstOrCreate(
                ['code' => $code],
                [
                    'description' => $code . ' Auto-created',
                    'default_vat_business_posting_group_id' => null,
                    'auto_create_vat_bus_posting_group' => false,
                    'blocked' => false,
                ]
            );

            $groups[$code] = $group->id;
        }

        return $groups;
    }
}
