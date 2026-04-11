<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShippingAgentServiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingAgent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'search_name', 'address', 'address_2', 'city',
        'post_code', 'country_code', 'phone_no', 'email', 'website',
        'account_no', 'api_key', 'api_endpoint', 'default_service_type',
        'default_insurance_amount', 'requires_insurance', 'base_charge',
        'fuel_surcharge_percent', 'handling_charge', 'shortcut_dimension_1_code',
        'shortcut_dimension_2_code', 'is_active', 'blocked', 'notes', 'extended_fields',
    ];

    protected $casts = [
        'default_service_type' => ShippingAgentServiceType::class,
        'default_insurance_amount' => 'decimal:4',
        'base_charge' => 'decimal:4',
        'fuel_surcharge_percent' => 'decimal:2',
        'handling_charge' => 'decimal:4',
        'is_active' => 'boolean',
        'blocked' => 'boolean',
        'extended_fields' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($agent) {
            if (empty($agent->search_name)) {
                $agent->search_name = $agent->name;
            }
        });
    }

    public function services(): HasMany
    {
        return $this->hasMany(ShippingAgentService::class);
    }

    public function activeServices(): HasMany
    {
        return $this->hasMany(ShippingAgentService::class)->where('is_active', true);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'shipping_agent_code', 'code');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('blocked', false);
    }

    public function canUse(): bool
    {
        return $this->is_active && ! $this->blocked;
    }

    public function getDefaultService(): ?ShippingAgentService
    {
        return $this->activeServices()
            ->where('service_type', $this->default_service_type)
            ->first();
    }

    public function calculateFreight(float $weight, float $length, float $width, float $height, string $serviceCode): float
    {
        $service = $this->services()->where('service_code', $serviceCode)->first();

        if (! $service) {
            return $this->base_charge;
        }

        // Dimensional weight calculation
        $dimWeight = ($length * $width * $height) / $service->dimensional_factor;
        $chargeableWeight = max($weight, $dimWeight);

        $freight = $service->base_charge + ($chargeableWeight * $service->weight_rate);
        $fuelSurcharge = $freight * ($this->fuel_surcharge_percent / 100);

        return $freight + $fuelSurcharge + $this->handling_charge;
    }
}
