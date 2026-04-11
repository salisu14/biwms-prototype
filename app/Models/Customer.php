<?php

// app/Models/Customer.php

namespace App\Models;

use App\Enums\CustomerType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_number',
        'name',
        'address',
        'email',
        'phone',
        'general_business_posting_group_id',
        'customer_posting_group_id',
        'vat_business_posting_group_id',
        'vat_bus_posting_group',
        'customer_type',
        'location_id',
        'shipping_agent_code',
        'payment_terms_code',
        'credit_limit',
        'blocked',
        'blocked_reason',
        'contact_id',
        'is_price_inclusive',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'blocked' => 'boolean',
        'customer_type' => CustomerType::class,
        'is_price_inclusive' => 'boolean',
    ];

    // Relationships
    public function group()
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function generalBusinessPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralBusinessPostingGroup::class);
    }

    public function customerPostingGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerPostingGroup::class);
    }

    public function vatBusinessPostingGroup(): BelongsTo
    {
        return $this->belongsTo(VatBusinessPostingGroup::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function getNameAttribute(): string
    {
        return $this->contact?->name
            ?? $this->attributes['name']
            ?? $this->customer_number
            ?? 'Unnamed Customer';
    }

    public function warehouseShipments(): HasMany
    {
        return $this->hasMany(WarehouseShipment::class);
    }

    // Get posting setup for an item
    public function getPostingSetupFor(Item $item): ?GeneralPostingSetup
    {
        return GeneralPostingSetup::where([
            'general_business_posting_group_id' => $this->general_business_posting_group_id,
            'general_product_posting_group_id' => $item->general_product_posting_group_id,
        ])->first();
    }

    // Get sales account for item
    public function getSalesAccountFor(Item $item): ?ChartOfAccount
    {
        $setup = $this->getPostingSetupFor($item);

        return $setup?->getSalesAccount();
    }

    // Get COGS account for item
    public function getCogsAccountFor(Item $item): ?ChartOfAccount
    {
        $setup = $this->getPostingSetupFor($item);

        return $setup?->getCogsAccount();
    }

    // Get A/R account
    public function getReceivablesAccount(): ChartOfAccount
    {
        return $this->customerPostingGroup->receivablesAccount;
    }

    // Check if fully blocked
    public function isFullyBlocked(): bool
    {
        return $this->blocked && $this->blocked_reason === 'ALL';
    }

    // Check if blocked for posting
    public function isBlockedForPosting(): bool
    {
        return $this->blocked && in_array($this->blocked_reason, ['INVOICE', 'ALL']);
    }

    // Scope
    public function scopeActive($query)
    {
        return $query->where('blocked', false);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CustomerLedgerEntry::class)
            ->orderBy('entry_number');
    }

    public function openLedgerEntries(): HasMany
    {
        return $this->hasMany(CustomerLedgerEntry::class)
            ->where('open', true)
            ->orderBy('due_date');
    }

    // Balance calculations
    public function getBalanceAttribute(): float
    {
        return CustomerLedgerEntry::getBalance($this->id);
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
        return CustomerLedgerEntry::getAging($this->id);
    }

    // Credit limit check
    public function isOverCreditLimit(): bool
    {
        if (! $this->credit_limit) {
            return false;
        }

        return $this->balance > $this->credit_limit;
    }

    public function getAvailableCreditAttribute(): ?float
    {
        if (! $this->credit_limit) {
            return null;
        }

        return max(0, $this->credit_limit - $this->balance);
    }

    public function scopeNotBlockedFor($query, string $action)
    {
        return $query->where(function ($q) use ($action) {
            $q->where('blocked', false)
                ->orWhere(function ($sq) use ($action) {
                    if ($action === 'SHIP') {
                        $sq->where('blocked_reason', '!=', 'SHIP')
                            ->where('blocked_reason', '!=', 'ALL');
                    } elseif ($action === 'INVOICE') {
                        $sq->where('blocked_reason', '!=', 'INVOICE')
                            ->where('blocked_reason', '!=', 'ALL');
                    }
                });
        });
    }
}
