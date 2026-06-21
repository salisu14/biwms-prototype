<?php

namespace App\Models;

use App\Enums\ShippingAgentServiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingAgentService extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipping_agent_id', 'service_code', 'description', 'service_type',
        'transit_time_days', 'guaranteed_delivery', 'cutoff_time',
        'base_charge', 'weight_rate', 'dimensional_factor',
        'max_weight', 'max_length', 'international', 'available_countries', 'is_active',
    ];

    protected $casts = [
        'service_type' => ShippingAgentServiceType::class,
        'transit_time_days' => 'integer',
        'guaranteed_delivery' => 'boolean',
        'cutoff_time' => 'datetime:H:i',
        'base_charge' => 'decimal:4',
        'weight_rate' => 'decimal:4',
        'dimensional_factor' => 'decimal:4',
        'max_weight' => 'decimal:4',
        'max_length' => 'decimal:4',
        'international' => 'boolean',
        'available_countries' => 'array',
        'is_active' => 'boolean',
    ];

    public function shippingAgent(): BelongsTo
    {
        return $this->belongsTo(ShippingAgent::class);
    }

    public function getEstimatedDelivery(\DateTime $shipDate): \DateTime
    {
        return (clone $shipDate)->modify("+{$this->transit_time_days} weekdays");
    }
}
