<?php

// app/Models/PricingMaster.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PricingMaster extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pricing_master';

    protected $fillable = [
        'price_list_code',
        'description',
        'price_list_type',
        'customer_id',
        'pricing_group_id',
        'item_id',
        'variant_code',
        'unit_of_measure_code',
        'location_id',
        'currency_code',
        'price_type',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'cost_plus_percent',
        'minimum_quantity',
        'maximum_quantity',
        'allow_quantity_breaks',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'applicable_days',
        'minimum_order_amount',
        'minimum_order_quantity',
        'minimum_lead_time_days',
        'status',
        'approved_by',
        'approved_at',
        'created_by',
        'modified_by',
        'modification_reason',
        'priority',
        'is_current_version',
        'replaces_id',
        'replaced_by_id',
    ];

    protected $casts = [
        'unit_price' => 'decimal:4',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:4',
        'cost_plus_percent' => 'decimal:2',
        'minimum_quantity' => 'decimal:4',
        'maximum_quantity' => 'decimal:4',
        'minimum_order_amount' => 'decimal:2',
        'minimum_order_quantity' => 'decimal:4',
        'minimum_lead_time_days' => 'integer',
        'applicable_days' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'approved_at' => 'datetime',
        'is_current_version' => 'boolean',
        'allow_quantity_breaks' => 'boolean',
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function pricingGroup(): BelongsTo
    {
        return $this->belongsTo(PricingGroup::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function quantityBreaks(): HasMany
    {
        return $this->hasMany(PricingMasterQuantityBreak::class)->orderBy('line_number');
    }

    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_id');
    }

    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by');
    }

    // Check if this price is effective now
    public function isEffective(?\DateTime $date = null): bool
    {
        $checkDate = $date ?? now();

        if ($this->status !== 'ACTIVE') {
            return false;
        }

        // Date check
        if ($this->start_date > $checkDate->toDateString()) {
            return false;
        }

        if ($this->end_date && $this->end_date < $checkDate->toDateString()) {
            return false;
        }

        // Time check (if specified)
        if ($this->start_time || $this->end_time) {
            $checkTime = $checkDate->format('H:i');

            if ($this->start_time && $checkTime < $this->start_time->format('H:i')) {
                return false;
            }

            if ($this->end_time && $checkTime > $this->end_time->format('H:i')) {
                return false;
            }
        }

        // Day of week check
        if ($this->applicable_days) {
            $dayName = strtolower($checkDate->format('D')); // 'mon', 'tue'
            if (! in_array($dayName, $this->applicable_days)) {
                return false;
            }
        }

        return true;
    }

    // Check if quantity is valid for this price
    public function isValidQuantity(float $quantity): bool
    {
        if ($quantity < $this->minimum_quantity) {
            return false;
        }

        if ($this->maximum_quantity && $quantity > $this->maximum_quantity) {
            return false;
        }

        return true;
    }

    // Get applicable quantity break
    public function getQuantityBreak(float $quantity): ?PricingMasterQuantityBreak
    {
        if (! $this->allow_quantity_breaks) {
            return null;
        }

        return $this->quantityBreaks()
            ->where('minimum_quantity', '<=', $quantity)
            ->where(function ($q) use ($quantity) {
                $q->whereNull('maximum_quantity')
                    ->orWhere('maximum_quantity', '>=', $quantity);
            })
            ->orderBy('minimum_quantity', 'desc')
            ->first();
    }

    // Calculate final price
    public function calculatePrice(
        float $quantity,
        ?float $baseCost = null,
        ?float $listPrice = null
    ): array {
        $result = [
            'base_price' => 0,
            'discount_amount' => 0,
            'discount_percent' => 0,
            'final_price' => 0,
            'price_source' => $this->price_list_code,
            'quantity_break_applied' => false,
        ];

        // Check quantity breaks first
        $break = $this->getQuantityBreak($quantity);

        if ($break) {
            $result['quantity_break_applied'] = true;

            if ($break->unit_price !== null) {
                $result['base_price'] = $break->unit_price;
            } elseif ($break->discount_percent !== null) {
                $result['discount_percent'] = $break->discount_percent;
            } elseif ($break->discount_amount !== null) {
                $result['discount_amount'] = $break->discount_amount;
            }
        } else {
            // Use header price
            switch ($this->price_type) {
                case 'UNIT_PRICE':
                    $result['base_price'] = $this->unit_price;
                    break;

                case 'PERCENT_DISCOUNT':
                    $result['discount_percent'] = $this->discount_percent;
                    break;

                case 'AMOUNT_DISCOUNT':
                    $result['discount_amount'] = $this->discount_amount;
                    break;

                case 'COST_PLUS_PERCENT':
                    if ($baseCost) {
                        $result['base_price'] = $baseCost * (1 + $this->cost_plus_percent / 100);
                    }
                    break;

                case 'COST_PLUS_AMOUNT':
                    if ($baseCost) {
                        $result['base_price'] = $baseCost + $this->cost_plus_percent; // stored in this field
                    }
                    break;

                case 'FORMULA':
                    // Would call pricing engine service
                    break;
            }
        }

        // Apply discounts
        if ($result['discount_percent'] > 0 && $result['base_price'] > 0) {
            $result['discount_amount'] = $result['base_price'] * ($result['discount_percent'] / 100);
        }

        $result['final_price'] = $result['base_price'] - $result['discount_amount'];

        // Ensure non-negative (unless allowed)
        if ($result['final_price'] < 0 && ! $this->item?->allow_negative_price) {
            $result['final_price'] = 0;
        }

        return $result;
    }

    // Get posting group alignment
    public function getBusinessPostingGroup(): ?GeneralBusinessPostingGroup
    {
        if ($this->customer) {
            return $this->customer->generalBusinessPostingGroup;
        }

        if ($this->pricingGroup) {
            return $this->pricingGroup->generalBusinessPostingGroup;
        }

        return null;
    }

    // Static method: Find best price using hierarchy
    public static function getBestPrice(
        Item $item,
        ?Customer $customer = null,
        ?PricingGroup $pricingGroup = null,
        ?string $variantCode = null,
        ?string $uom = null,
        float $quantity = 1,
        ?string $currency = null,
        ?Location $location = null,
        ?\DateTime $date = null
    ): ?self {
        $currency = $currency ?? config('app.default_currency', 'USD');
        $date = $date ?? now();

        // Build query with priority order
        $query = self::where('status', 'ACTIVE')
            ->where('is_current_version', true)
            ->where('item_id', $item->id)
            ->where(function ($q) use ($variantCode) {
                $q->whereNull('variant_code')
                    ->orWhere('variant_code', $variantCode);
            })
            ->where(function ($q) use ($uom) {
                $q->whereNull('unit_of_measure_code')
                    ->orWhere('unit_of_measure_code', $uom);
            })
            ->where(function ($q) use ($currency) {
                $q->where('currency_code', $currency)
                    ->orWhere('currency_code', 'USD'); // Fallback
            })
            ->where('start_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            })
            ->where('minimum_quantity', '<=', $quantity)
            ->where(function ($q) use ($quantity) {
                $q->whereNull('maximum_quantity')
                    ->orWhere('maximum_quantity', '>=', $quantity);
            })
            ->orderBy('priority', 'desc')
            ->orderBy('start_date', 'desc');

        // Customer-specific takes precedence
        if ($customer) {
            $customerPrice = (clone $query)
                ->where('price_list_type', 'CUSTOMER')
                ->where('customer_id', $customer->id)
                ->first();

            if ($customerPrice) {
                return $customerPrice;
            }

            // Try customer's pricing group
            if ($customer->pricing_group_id) {
                $groupPrice = (clone $query)
                    ->where('price_list_type', 'CUSTOMER_GROUP')
                    ->where('pricing_group_id', $customer->pricing_group_id)
                    ->first();

                if ($groupPrice) {
                    return $groupPrice;
                }
            }
        }

        // Specific pricing group requested
        if ($pricingGroup) {
            $specificGroupPrice = (clone $query)
                ->where('price_list_type', 'CUSTOMER_GROUP')
                ->where('pricing_group_id', $pricingGroup->id)
                ->first();

            if ($specificGroupPrice) {
                return $specificGroupPrice;
            }
        }

        // Campaign/special prices
        $campaignPrice = (clone $query)
            ->where('price_list_type', 'CAMPAIGN')
            ->first();

        if ($campaignPrice) {
            return $campaignPrice;
        }

        // General price list
        return (clone $query)
            ->where('price_list_type', 'ALL_CUSTOMERS')
            ->first();
    }

    // Scope
    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    public function scopeCurrentVersion($query)
    {
        return $query->where('is_current_version', true);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForItem($query, int $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    public function scopeEffectiveOn($query, $date)
    {
        return $query->where('start_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            });
    }
}
