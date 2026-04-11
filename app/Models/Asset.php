<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssetType;
use App\Enums\FixedAssetCategory;
use App\Enums\IntangibleAssetType;
use App\Enums\LiquidityAssetType;
use App\Enums\TangibleAssetType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        // Classification
        'asset_type', 'fixed_asset_category', 'tangible_type', 'intangible_type', 'liquidity_type',

        // Identification
        'asset_no', 'description', 'description_2', 'search_name', 'currency_id',

        // Cash/Bank specific
        'bank_account_id', 'bank_account_no', 'account_holder_name', 'bank_name',
        'branch_code', 'iban', 'swift_code',

        // Receivables/Advances specific
        'vendor_id', 'customer_id', 'employee_id', 'reference_document_no', 'expected_clearance_date',

        // Fixed asset specific
        'fa_location_code', 'serial_no', 'registration_no', 'main_asset_id',

        // Acquisition
        'acquisition_date', 'acquisition_cost', 'original_cost',
        'acquisition_vendor_id', 'purchase_order_no', 'purchase_invoice_no',

        // Status
        'active', 'acquired',

        // Depreciation
        'depreciation_method', 'depreciation_start_date', 'depreciation_end_date',
        'useful_life_months', 'salvage_value', 'depreciation_rate',
        'book_value', 'accumulated_depreciation', 'last_depreciation_date',

        // Liquidity balances
        'opening_balance', 'current_balance', 'currency_code', 'currency_factor',
        'last_reconciliation_date',

        // GL accounts
        'fa_posting_group_id', 'asset_account_id', 'accum_dep_account_id',
        'depreciation_expense_account_id', 'gain_loss_account_id',

        // Dimensions
        'shortcut_dimension_1_code', 'shortcut_dimension_2_code', 'dimensions',

        // Disposal
        'disposal_date', 'disposal_proceeds', 'gain_loss_on_disposal',

        'notes', 'custom_attributes',
    ];

    protected $casts = [
        'asset_type' => AssetType::class,
        'fixed_asset_category' => FixedAssetCategory::class,
        'tangible_type' => TangibleAssetType::class,
        'intangible_type' => IntangibleAssetType::class,
        'liquidity_type' => LiquidityAssetType::class,

        'acquisition_date' => 'date',
        'acquisition_cost' => 'decimal:4',
        'original_cost' => 'decimal:4',
        'active' => 'boolean',
        'acquired' => 'boolean',

        'depreciation_start_date' => 'date',
        'depreciation_end_date' => 'date',
        'useful_life_months' => 'integer',
        'salvage_value' => 'decimal:4',
        'depreciation_rate' => 'decimal:4',
        'book_value' => 'decimal:4',
        'accumulated_depreciation' => 'decimal:4',
        'last_depreciation_date' => 'date',

        'opening_balance' => 'decimal:4',
        'current_balance' => 'decimal:4',
        'currency_factor' => 'decimal:6',
        'last_reconciliation_date' => 'date',
        'expected_clearance_date' => 'date',

        'disposal_date' => 'date',
        'disposal_proceeds' => 'decimal:4',
        'gain_loss_on_disposal' => 'decimal:4',

        'currency_id' => 'integer',
        'dimensions' => 'array',
        'custom_attributes' => 'array',
    ];

    // Relationships
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function acquisitionVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'acquisition_vendor_id');
    }

    public function mainAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'main_asset_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(AssetComponent::class, 'main_asset_id');
    }

    public function componentOf(): HasMany
    {
        return $this->hasMany(AssetComponent::class, 'component_asset_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(AssetLedgerEntry::class)->orderBy('posting_date');
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(AssetMaintenance::class);
    }

    public function postingGroup(): BelongsTo
    {
        return $this->belongsTo(FAPostingGroup::class, 'fa_posting_group_id');
    }

    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'asset_account_id');
    }

    public function accumDepAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'accum_dep_account_id');
    }

    public function depExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'depreciation_expense_account_id');
    }

    public function gainLossAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'gain_loss_account_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'fa_location_code', 'code');
    }

    // Scopes by classification
    public function scopeFixedAssets($query)
    {
        return $query->where('asset_type', AssetType::FIXED);
    }

    public function scopeLiquidityAssets($query)
    {
        return $query->where('asset_type', AssetType::LIQUIDITY);
    }

    public function scopeTangible($query)
    {
        return $query->where('fixed_asset_category', FixedAssetCategory::TANGIBLE);
    }

    public function scopeIntangible($query)
    {
        return $query->where('fixed_asset_category', FixedAssetCategory::INTANGIBLE);
    }

    public function scopeByTangibleType($query, TangibleAssetType $type)
    {
        return $query->where('tangible_type', $type);
    }

    public function scopeByLiquidityType($query, LiquidityAssetType $type)
    {
        return $query->where('liquidity_type', $type);
    }

    public function scopeCashAndBank($query)
    {
        return $query->where('liquidity_type', LiquidityAssetType::CASH_HAND)
            ->orWhere('liquidity_type', LiquidityAssetType::CASH_BANK);
    }

    public function scopeReceivables($query)
    {
        return $query->whereIn('liquidity_type', [
            LiquidityAssetType::ACCOUNTS_RECEIVABLE,
            LiquidityAssetType::ADVANCE_VENDOR,
            LiquidityAssetType::ADVANCE_STAFF,
        ]);
    }

    // Classification helpers
    public function isFixedAsset(): bool
    {
        return $this->asset_type === AssetType::FIXED;
    }

    public function isLiquidityAsset(): bool
    {
        return $this->asset_type === AssetType::LIQUIDITY;
    }

    public function isLiquidity(): bool
    {
        return $this->isLiquidityAsset();
    }

    public function isTangible(): bool
    {
        return $this->fixed_asset_category === FixedAssetCategory::TANGIBLE;
    }

    public function isIntangible(): bool
    {
        return $this->fixed_asset_category === FixedAssetCategory::INTANGIBLE;
    }

    public function isPlantMachinery(): bool
    {
        return $this->tangible_type === TangibleAssetType::PLANT_MACHINERY;
    }

    public function isBuilding(): bool
    {
        return $this->tangible_type === TangibleAssetType::BUILDING;
    }

    public function isVehicle(): bool
    {
        return $this->tangible_type === TangibleAssetType::VEHICLE;
    }

    public function isCashInHand(): bool
    {
        return $this->liquidity_type === LiquidityAssetType::CASH_HAND;
    }

    public function isCashInBank(): bool
    {
        return $this->liquidity_type === LiquidityAssetType::CASH_BANK;
    }

    public function isAdvanceToVendor(): bool
    {
        return $this->liquidity_type === LiquidityAssetType::ADVANCE_VENDOR;
    }

    // Business logic
    public function toLCY(float $amount, ?\DateTime $date = null): float
    {
        if ($this->currency) {
            return $this->currency->toLCY($amount, null, $date);
        }

        return $amount; // Assume LCY if no currency set
    }

    public function fromLCY(float $amountLCY, ?\DateTime $date = null): float
    {
        if ($this->currency) {
            return $this->currency->fromLCY($amountLCY, null, $date);
        }

        return $amountLCY;
    }

    public function calculateGainLossOnDisposal(float $proceeds): float
    {
        if (! $this->isFixedAsset()) {
            return 0;
        }

        return $proceeds - $this->book_value;
    }

    public function calculateForexAdjustment(float $newExchangeRate): float
    {
        if (! $this->isLiquidityAsset() || ! $this->currency_id) {
            return 0;
        }

        $oldValueLCY = $this->toLCY($this->current_balance);
        $newValueLCY = $this->current_balance * $newExchangeRate;

        return $newValueLCY - $oldValueLCY;
    }

    public function isDepreciable(): bool
    {
        if (! $this->isFixedAsset()) {
            return false;
        }

        if ($this->isTangible()) {
            return $this->tangible_type?->isDepreciable() ?? false;
        }

        if ($this->isIntangible()) {
            return $this->intangible_type?->isDefiniteLife() ?? false;
        }

        return false;
    }

    public function requiresMaintenance(): bool
    {
        return $this->isTangible() && ($this->tangible_type?->requiresMaintenance() ?? false);
    }

    public function getDisplayType(): string
    {
        return match ($this->asset_type) {
            AssetType::FIXED => $this->isTangible()
                ? ($this->tangible_type?->label() ?? 'Tangible')
                : ($this->intangible_type?->label() ?? 'Intangible'),
            AssetType::LIQUIDITY => $this->liquidity_type?->label() ?? 'Liquidity',
        } ?? 'Unknown';
    }

    public function getRemainingUsefulLife(): ?int
    {
        if (! $this->depreciation_end_date) {
            return null;
        }

        return max(0, now()->diffInMonths($this->depreciation_end_date, false));
    }

    public function getNetBookValue(): float
    {
        if ($this->isLiquidityAsset()) {
            return (float) $this->current_balance;
        }

        return (float) $this->book_value;
    }

    /**
     * Calculate depreciation for a specific period (Merged from legacy FixedAsset)
     */
    public function calculateDepreciation(string $period): float
    {
        if (! $this->isDepreciable()) {
            return 0;
        }

        return match ($this->depreciation_method) {
            'STRAIGHT_LINE' => $this->calculateStraightLineDepreciation($period),
            'DECLINING_BALANCE' => $this->calculateDecliningBalanceDepreciation($period),
            default => 0,
        };
    }

    protected function calculateStraightLineDepreciation(string $period): float
    {
        if (! $this->useful_life_months) {
            return 0;
        }
        $depreciableAmount = (float) $this->acquisition_cost - (float) $this->salvage_value;

        return $depreciableAmount / $this->useful_life_months;
    }

    protected function calculateDecliningBalanceDepreciation(string $period): float
    {
        $rate = (float) $this->depreciation_rate / 100;

        return (float) $this->book_value * $rate / 12;
    }
}
