<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceContractSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'maintenance_contract_id',
        'fixed_asset_id',
        'frequency',
        'interval_months',
        'first_service_date',
        'last_service_date',
        'next_service_date',
        'service_description',
        'estimated_cost',
        'is_active',
    ];

    protected $casts = [
        'first_service_date' => 'date',
        'last_service_date' => 'date',
        'next_service_date' => 'date',
        'estimated_cost' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function maintenanceContract(): BelongsTo
    {
        return $this->belongsTo(MaintenanceContract::class, 'maintenance_contract_id');
    }

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function isDue(): bool
    {
        return $this->next_service_date <= now();
    }

    public function calculateNextDate(): \Carbon\Carbon
    {
        $base = $this->last_service_date
            ? \Carbon\Carbon::parse($this->last_service_date)
            : \Carbon\Carbon::parse($this->first_service_date);

        return match($this->frequency) {
            'weekly' => $base->addWeek(),
            'monthly' => $base->addMonths($this->interval_months),
            'quarterly' => $base->addQuarter(),
            'semi_annual' => $base->addMonths(6),
            'annual' => $base->addYear(),
            default => $base->addMonth(),
        };
    }

    public function completeService(\DateTime $serviceDate): void
    {
        $this->update([
            'last_service_date' => $serviceDate,
            'next_service_date' => $this->calculateNextDate(),
        ]);

        $this->maintenanceContract->update([
            'last_service_date' => $serviceDate,
        ]);
    }
}
