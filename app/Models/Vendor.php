<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_code',
        'vendor_name',
        'contact_person',
        'email',
        'phone',
        'mobile',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'tax_id',
        'payment_terms',
        'currency',
        'lead_time_days',
        'minimum_order_amount',
        'is_active',
        'general_business_posting_group_id',
        'vendor_posting_group_id',
        'vat_bus_posting_group',
        'payment_terms_code',
        'blocked',
        'blocked_reason',
        'contact_id',
        'notes',
    ];

    protected $casts = [
        'lead_time_days' => 'integer',
        'minimum_order_amount' => 'decimal:4',
        'is_active' => 'boolean',
        'blocked' => 'boolean',
    ];

    protected $appends = [
        'balance',
        'open_balance',
        'overdue_balance',
        'aging',
        'available_discounts',
        'total_available_discount',
        'is_overpaid',
        'available_credit',
    ];

    // Relationships
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function generalBusinessPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralBusinessPostingGroup::class);
    }

    public function vendorPostingGroup(): BelongsTo
    {
        return $this->belongsTo(VendorPostingGroup::class);
    }

    public function warehouseReceipts(): HasMany
    {
        return $this->hasMany(WarehouseReceipt::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(VendorLedgerEntry::class)
            ->orderBy('entry_number');
    }

    public function getNameAttribute(): ?string
    {
        return $this->contact?->name;
    }

    public function openLedgerEntries(): HasMany
    {
        return $this->hasMany(VendorLedgerEntry::class)
            ->where('open', true)
            ->orderBy('due_date');
    }

    // Get posting setup for an item
    public function getPostingSetupFor(Item $item): ?GeneralPostingSetup
    {
        return GeneralPostingSetup::where([
            'general_business_posting_group_id' => $this->general_business_posting_group_id,
            'general_product_posting_group_id' => $item->general_product_posting_group_id,
        ])->first();
    }

    // Get purchase account for item
    public function getPurchaseAccountFor(Item $item): ?ChartOfAccount
    {
        $setup = $this->getPostingSetupFor($item);

        return $setup?->getPurchaseAccount();
    }

    // Get A/P account
    public function getPayablesAccount(): ?ChartOfAccount
    {
        return $this->vendorPostingGroup?->payablesAccount;
    }

    // Balance calculations
    public function getBalanceAttribute(): float
    {
        return VendorLedgerEntry::getBalance($this->id);
    }

    public function getOpenBalanceAttribute(): float
    {
        return $this->ledgerEntries()
            ->where('open', true)
            ->sum('remaining_amount');
    }

    public function getOverdueBalanceAttribute(): float
    {
        return $this->ledgerEntries()
            ->overdue()
            ->sum('remaining_amount');
    }

    public function getAgingAttribute(): array
    {
        return VendorLedgerEntry::getAging($this->id);
    }

    // Available discounts (early payment opportunities)
    public function getAvailableDiscountsAttribute(): array
    {
        return VendorLedgerEntry::getAvailableDiscounts($this->id);
    }

    public function getTotalAvailableDiscountAttribute(): float
    {
        return collect($this->available_discounts)->sum('discount_amount');
    }

    // Credit status
    public function getIsOverpaidAttribute(): bool
    {
        return $this->balance < 0; // Negative balance = we have credit
    }

    public function getAvailableCreditAttribute(): float
    {
        return max(0, -$this->balance);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('blocked', false)->where('is_active', true);
    }

    public function scopeBlocked($query)
    {
        return $query->where('blocked', true);
    }

    public function scopeWithOpenBalance($query)
    {
        return $query->whereHas('ledgerEntries', function ($q) {
            $q->where('open', true);
        });
    }
}
