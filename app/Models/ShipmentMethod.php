<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShipmentMethod extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'description',
        'search_description',
        'incoterm_code',
        'is_incoterm',
        'transport_mode',
        'seller_pays_insurance',
        'seller_pays_freight',
        'seller_pays_duty',
        'default_shipping_agent_id',
        'default_service_code',
        'shortcut_dimension_1_code',
        'shortcut_dimension_2_code',
        'is_active',
        'blocked',
        'notes',
        'extended_fields',
    ];

    protected $casts = [
        'is_incoterm' => 'boolean',
        'seller_pays_insurance' => 'boolean',
        'seller_pays_freight' => 'boolean',
        'seller_pays_duty' => 'boolean',
        'is_active' => 'boolean',
        'blocked' => 'boolean',
        'extended_fields' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($method) {
            if (empty($method->search_description)) {
                $method->search_description = $method->description;
            }
        });
    }

    // Relationships
    public function defaultShippingAgent(): BelongsTo
    {
        return $this->belongsTo(ShippingAgent::class, 'default_shipping_agent_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'shipment_method_code', 'code');
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class, 'shipment_method_code', 'code');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'shipment_method_code', 'code');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'shipment_method_code', 'code');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('blocked', false);
    }

    public function scopeIncoterms($query)
    {
        return $query->where('is_incoterm', true);
    }

    public function scopeByTransportMode($query, string $mode)
    {
        return $query->where('transport_mode', $mode);
    }

    // Accessors
    public function getDisplayNameAttribute(): string
    {
        return "{$this->code} - {$this->description}";
    }

    public function getResponsibilityMatrixAttribute(): array
    {
        return [
            'export_clearance' => $this->is_incoterm && in_array($this->incoterm_code, ['EXW', 'FCA', 'FAS', 'FOB']) ? 'buyer' : 'seller',
            'import_clearance' => $this->is_incoterm && in_array($this->incoterm_code, ['DDP', 'DAP', 'DAT']) ? 'seller' : 'buyer',
            'main_carriage' => $this->seller_pays_freight ? 'seller' : 'buyer',
            'insurance' => $this->seller_pays_insurance ? 'seller' : 'buyer',
            'duty' => $this->seller_pays_duty ? 'seller' : 'buyer',
        ];
    }

    // Methods
    public function canUse(): bool
    {
        return $this->is_active && !$this->blocked;
    }

    public function block(): void
    {
        $this->update(['blocked' => true]);
    }

    public function unblock(): void
    {
        $this->update(['blocked' => false]);
    }

    /**
     * Validate if method is compatible with given countries (for Incoterms)
     */
    public function isValidForRoute(?string $originCountry, ?string $destinationCountry): bool
    {
        if (!$this->is_incoterm) {
            return true;
        }

        // DDP validation: seller must be able to handle import clearance
        if ($this->incoterm_code === 'DDP' && $originCountry === $destinationCountry) {
            return false; // DDP doesn't make sense for domestic
        }

        return true;
    }
}
