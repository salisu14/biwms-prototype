<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model for FA Maintenance Logs
 */
class FAMaintenanceLog extends Model
{
    use HasFactory;

    protected $table = 'fa_maintenance_logs';

    protected $fillable = [
        'fixed_asset_id', 'service_date', 'service_type', 'description',
        'cost', 'capitalized', 'vendor_id', 'maintenance_contract_id',
        'next_service_date', 'created_by',
    ];

    protected $casts = [
        'service_date' => 'date',
        'next_service_date' => 'date',
        'cost' => 'decimal:4',
        'capitalized' => 'boolean',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'fixed_asset_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    // In FAMaintenanceLog model
    public function maintenanceContract(): BelongsTo
    {
        return $this->belongsTo(MaintenanceContract::class, 'maintenance_contract_id');
    }

    // Optional: Helper to check if service was covered by contract
    public function isCoveredByContract(): bool
    {
        return $this->maintenance_contract_id !== null
            && $this->maintenanceContract?->isActive();
    }

    // Get applicable discount from contract
    public function getContractDiscountPercent(): float
    {
        return $this->maintenanceContract?->parts_discount_percent ?? 0;
    }
}
