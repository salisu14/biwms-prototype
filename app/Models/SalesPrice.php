<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SalesPriceSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Currency-tagged sales price.
 *
 * This is the provenance-safe replacement direction for representing a
 * negotiated price (for example USD 220) independently of an LCY item
 * reference price (for example NGN 300,000). It stores the price in its own
 * currency; no FX conversion is performed or implied here.
 *
 * Phase 3A introduces this schema only; existing pricing resolution is not
 * wired to it yet.
 */
class SalesPrice extends Model
{
    use HasFactory;

    protected $table = 'sales_prices';

    protected $fillable = [
        'item_id',
        'customer_id',
        'customer_group_id',
        'unit_of_measure_code',
        'currency_code',
        'price',
        'source',
        'effective_from',
        'effective_to',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'price' => 'decimal:4',
        'source' => SalesPriceSource::class,
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
        'item_id' => 'integer',
        'customer_id' => 'integer',
        'customer_group_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForItem(Builder $query, int $itemId): Builder
    {
        return $query->where('item_id', $itemId);
    }

    public function scopeInCurrency(Builder $query, string $currencyCode): Builder
    {
        return $query->where('currency_code', strtoupper(trim($currencyCode)));
    }
}
