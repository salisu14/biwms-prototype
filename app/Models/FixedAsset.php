<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DepreciationMethod;
use App\Enums\FAStatus;
use App\Enums\FixedAssetType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class FixedAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'fa_no',
        'description',
        'description_2',
        'search_description',
        'fa_type',
        'fa_class_id',
        'fa_subclass_id',
        'fa_location_id',
        'fa_posting_group_id',
        'depreciation_book_id',
        'serial_no',
        'barcode',
        'responsible_employee_id',
        'vendor_id',
        'main_vendor_id',
        'location_id',
        'fa_location_code',
        'acquisition_date',
        'depreciation_starting_date',
        'depreciation_ending_date',
        'acquisition_cost',
        'acquisition_vendor_id',
        'acquisition_invoice_no',
        'depreciation_method',
        'depreciation_rate',
        'useful_life_years',
        'useful_life_months',
        'salvage_value',
        'salvage_value_percentage',
        'total_estimated_units',
        'units_produced_to_date',
        'declining_balance_calc',
        'book_value',
        'accumulated_depreciation',
        'last_revaluation_amount',
        'last_revaluation_date',
        'revaluation_reserve',
        'insurance_value',
        'insurance_expiry_date',
        'insurance_policy_no',
        'status',
        'blocked',
        'blocked_reason',
        'disposal_date',
        'disposal_proceeds',
        'disposal_cost',
        'disposal_gain_loss',
        'shortcut_dimension_1_code',
        'shortcut_dimension_2_code',
        'dimension_set_entry',
        'created_by',
        'modified_by',
    ];

    protected $casts = [
        'fa_type' => FixedAssetType::class,
        'depreciation_method' => DepreciationMethod::class,
        'status' => FAStatus::class,
        'acquisition_date' => 'date',
        'depreciation_starting_date' => 'date',
        'depreciation_ending_date' => 'date',
        'last_revaluation_date' => 'date',
        'insurance_expiry_date' => 'date',
        'disposal_date' => 'date',
        'acquisition_cost' => 'decimal:4',
        'depreciation_rate' => 'decimal:4',
        'salvage_value' => 'decimal:4',
        'book_value' => 'decimal:4',
        'accumulated_depreciation' => 'decimal:4',
        'revaluation_reserve' => 'decimal:4',
        'insurance_value' => 'decimal:4',
        'disposal_proceeds' => 'decimal:4',
        'disposal_cost' => 'decimal:4',
        'disposal_gain_loss' => 'decimal:4',
        'dimension_set_entry' => 'array',
        'blocked' => 'boolean',
    ];

    /**
     * Boot the model to handle audit logging for created_by and modified_by.
     */
    protected static function booted(): void
    {
        static::creating(function (FixedAsset $asset) {
            if (Auth::check()) {
                $asset->created_by = Auth::id();
            }
        });

        static::saving(function (FixedAsset $asset) {
            if (Auth::check()) {
                $asset->modified_by = Auth::id();
            }
        });
    }

    // Relationships
    public function postingGroup(): BelongsTo
    {
        return $this->belongsTo(FAPostingGroup::class, 'fa_posting_group_id');
    }

    public function depreciationBook(): BelongsTo
    {
        return $this->belongsTo(DepreciationBook::class, 'depreciation_book_id');
    }

    public function faClass(): BelongsTo
    {
        return $this->belongsTo(FAClass::class, 'fa_class_id');
    }

    public function faSubclass(): BelongsTo
    {
        return $this->belongsTo(FASubclass::class, 'fa_subclass_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function mainVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'main_vendor_id');
    }

    public function responsibleEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_employee_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(FALedgerEntry::class, 'fixed_asset_id')
            ->orderBy('posting_date')
            ->orderBy('entry_no');
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(FAMaintenanceLog::class, 'fixed_asset_id');
    }

    // Calculated attributes
    public function getNetBookValueAttribute(): float
    {
        return (float) $this->book_value - (float) $this->accumulated_depreciation;
    }

    public function getRemainingLifeMonthsAttribute(): ?float
    {
        if (!$this->depreciation_ending_date) return null;
        return now()->diffInMonths($this->depreciation_ending_date, false);
    }

    public function isFullyDepreciated(): bool
    {
        return $this->net_book_value <= $this->salvage_value;
    }

    public function canDepreciate(): bool
    {
        return $this->status->canDepreciate()
            && !$this->isFullyDepreciated()
            && !$this->blocked;
    }

    // Business methods
    public function calculateDepreciationForPeriod(\DateTime $from, \DateTime $to): float
    {
        return app(\App\Services\FixedAsset\DepreciationCalculationService::class)
            ->calculate($this, $from, $to);
    }

    public function revalue(float $newAmount, \DateTime $date, string $reason): void
    {
        app(\App\Services\FixedAsset\RevaluationService::class)
            ->revalue($this, $newAmount, $date, $reason);
    }

    public function dispose(float $proceeds, \DateTime $date, string $disposalType): void
    {
        app(\App\Services\FixedAsset\DisposalService::class)
            ->dispose($this, $proceeds, $date, $disposalType);
    }
}
