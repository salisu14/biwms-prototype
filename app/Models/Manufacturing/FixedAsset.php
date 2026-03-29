<?php

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedAsset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'description',
        'asset_type',
        'acquisition_date',
        'acquisition_cost',
        'accumulated_depreciation',
        'net_book_value',
        'salvage_value',
        'useful_life_years',
        'depreciation_method',
        'annual_depreciation_amount',
        'depreciation_rate',
        'annual_capacity_minutes',
        'efficiency_percent',
        'total_square_footage',
        'parent_building_id',
        'status',
        'disposal_date',
        'disposal_proceeds',
        'asset_gl_account_id',
        'accumulated_depreciation_gl_account_id',
        'depreciation_expense_gl_account_id',
        'capex_project_id',
        'location_code',
        'responsible_employee_id',
        'created_by',
        'last_modified_by',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'disposal_date' => 'date',
        'acquisition_cost' => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'net_book_value' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'annual_depreciation_amount' => 'decimal:2',
        'depreciation_rate' => 'decimal:2',
        'efficiency_percent' => 'decimal:2',
        'total_square_footage' => 'decimal:2',
        'disposal_proceeds' => 'decimal:2',
    ];

    // Relationships

    public function capExProject(): BelongsTo
    {
        return $this->belongsTo(CapExProject::class);
    }

    public function parentBuilding(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'parent_building_id');
    }

    public function childAssets(): HasMany
    {
        return $this->hasMany(FixedAsset::class, 'parent_building_id');
    }

    public function workCenters(): BelongsToMany
    {
        return $this->belongsToMany(WorkCenter::class, 'fixed_asset_work_center')
            ->withPivot('allocation_percentage', 'installation_date', 'removal_date', 'allocation_basis')
            ->withTimestamps();
    }

    public function depreciationLedger(): HasMany
    {
        return $this->hasMany(FixedAssetDepreciationLedger::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function lastModifier(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'last_modified_by');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('asset_type', $type);
    }

    public function scopeInService($query)
    {
        return $query->whereNotNull('acquisition_date')
            ->where('status', 'ACTIVE');
    }

    // Business Logic

    /**
     * Calculate depreciation for a specific period
     */
    public function calculateDepreciation(string $period, ?float $unitsProduced = null): float
    {
        return match($this->depreciation_method) {
            'STRAIGHT_LINE' => $this->calculateStraightLineDepreciation($period),
            'DECLINING_BALANCE' => $this->calculateDecliningBalanceDepreciation($period),
            'UNITS_OF_PRODUCTION' => $this->calculateUnitsOfProductionDepreciation($unitsProduced),
            default => 0,
        };
    }

    protected function calculateStraightLineDepreciation(string $period): float
    {
        return $this->annual_depreciation_amount / 12; // Monthly
    }

    protected function calculateDecliningBalanceDepreciation(string $period): float
    {
        $rate = $this->depreciation_rate / 100;
        return $this->net_book_value * $rate / 12;
    }

    protected function calculateUnitsOfProductionDepreciation(?float $unitsProduced): float
    {
        if (!$unitsProduced || !$this->annual_capacity_minutes) {
            return 0;
        }

        $depreciableAmount = $this->acquisition_cost - $this->salvage_value;
        $ratePerUnit = $depreciableAmount / $this->annual_capacity_minutes;

        return $unitsProduced * $ratePerUnit;
    }

    /**
     * Get depreciation rate per minute for production cost allocation
     */
    public function getDepreciationRatePerMinute(): float
    {
        if (!$this->annual_capacity_minutes || $this->annual_capacity_minutes <= 0) {
            return 0;
        }

        return $this->annual_depreciation_amount / $this->annual_capacity_minutes;
    }

    /**
     * Update net book value after depreciation
     */
    public function applyDepreciation(float $amount): void
    {
        $this->increment('accumulated_depreciation', $amount);
        $this->decrement('net_book_value', $amount);
        $this->save();
    }

    /**
     * Check if asset is fully depreciated
     */
    public function isFullyDepreciated(): bool
    {
        return $this->net_book_value <= $this->salvage_value;
    }

    /**
     * Get remaining useful life in years
     */
    public function getRemainingUsefulLife(): float
    {
        if (!$this->acquisition_date) {
            return $this->useful_life_years;
        }

        $elapsedYears = $this->acquisition_date->diffInYears(now());
        return max(0, $this->useful_life_years - $elapsedYears);
    }
}
