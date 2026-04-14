<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MaintenanceContractBillingCycle;
use App\Enums\MaintenanceContractStatus;
use App\Enums\MaintenanceContractType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceContract extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'contract_no',
        'description',
        'external_reference',
        'contract_type',
        'status',
        'vendor_id',
        'responsible_employee_id',
        'start_date',
        'end_date',
        'renewal_date',
        'notice_period_days',
        'auto_renewal',
        'auto_renewal_period_months',
        'billing_cycle',
        'contract_value',
        'billing_amount',
        'currency_code',
        'hourly_rate',
        'parts_discount_percent',
        'max_incidents_per_year',
        'max_hours_per_year',
        'max_cost_per_year',
        'deductible_amount',
        'response_time_hours_critical',
        'response_time_hours_standard',
        'resolution_time_hours',
        'coverage_type',
        'fa_class_id',
        'fa_location_id',
        'expense_account_id',
        'prepaid_account_id',
        'accrual_account_id',
        'shortcut_dimension_1_code',
        'shortcut_dimension_2_code',
        'dimension_set_entry',
        'scope_of_work',
        'exclusions',
        'special_terms',
        'termination_conditions',
        'contract_document_path',
        'attachments',
        'total_incidents_logged',
        'total_cost_incurred',
        'last_service_date',
        'next_scheduled_review',
        'created_by',
        'approved_by',
        'approved_at',
        'modified_by',
    ];

    protected $casts = [
        'contract_type' => MaintenanceContractType::class,
        'status' => MaintenanceContractStatus::class,
        'billing_cycle' => MaintenanceContractBillingCycle::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'renewal_date' => 'date',
        'auto_renewal' => 'boolean',
        'parts_discount_percent' => 'decimal:2',
        'contract_value' => 'decimal:4',
        'billing_amount' => 'decimal:4',
        'hourly_rate' => 'decimal:4',
        'deductible_amount' => 'decimal:4',
        'max_cost_per_year' => 'decimal:4',
        'dimension_set_entry' => 'array',
        'attachments' => 'array',
        'last_service_date' => 'datetime',
        'next_scheduled_review' => 'datetime',
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function responsibleEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'responsible_employee_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }

    public function prepaidAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'prepaid_account_id');
    }

    public function faClass(): BelongsTo
    {
        return $this->belongsTo(FAClass::class, 'fa_class_id');
    }

    public function faLocation(): BelongsTo
    {
        return $this->belongsTo(FALocation::class, 'fa_location_id');
    }

    public function coveredAssets(): BelongsToMany
    {
        return $this->belongsToMany(FixedAsset::class, 'maintenance_contract_assets')
            ->withPivot(['covered_serial_no', 'special_conditions', 'asset_specific_limit'])
            ->withTimestamps();
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(FAMaintenanceLog::class, 'maintenance_contract_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(MaintenanceContractSchedule::class, 'maintenance_contract_id');
    }

    public function billings(): HasMany
    {
        return $this->hasMany(MaintenanceContractBilling::class, 'maintenance_contract_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', MaintenanceContractStatus::ACTIVE);
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('end_date', '<=', now()->addDays($days))
            ->where('end_date', '>=', now());
    }

    public function scopeForAsset($query, int $fixedAssetId)
    {
        return $query->whereHas('coveredAssets', function ($q) use ($fixedAssetId) {
            $q->where('fixed_asset_id', $fixedAssetId);
        });
    }

    // Business methods
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isExpired(): bool
    {
        return $this->end_date < now();
    }

    public function daysUntilExpiry(): int
    {
        return now()->diffInDays($this->end_date, false);
    }

    public function canLogIncident(): bool
    {
        if (!$this->status->canCreateLogs()) {
            return false;
        }

        // Check incident limits
        if ($this->max_incidents_per_year && $this->total_incidents_logged >= $this->max_incidents_per_year) {
            return false;
        }

        // Check cost limits
        if ($this->max_cost_per_year && $this->total_cost_incurred >= $this->max_cost_per_year) {
            return false;
        }

        return true;
    }

    public function remainingContractValue(): float
    {
        return max(0, (float) $this->contract_value - (float) $this->total_cost_incurred);
    }

    public function getNextBillingDate(): ?\Carbon\Carbon
    {
        $lastBilling = $this->billings()->whereNotNull('actual_invoice_date')->latest('billing_date')->first();

        if (!$lastBilling) {
            return \Carbon\Carbon::parse($this->start_date);
        }

        return match($this->billing_cycle) {
            MaintenanceContractBillingCycle::MONTHLY => \Carbon\Carbon::parse($lastBilling->billing_date)->addMonth(),
            MaintenanceContractBillingCycle::QUARTERLY => \Carbon\Carbon::parse($lastBilling->billing_date)->addQuarter(),
            MaintenanceContractBillingCycle::SEMI_ANNUAL => \Carbon\Carbon::parse($lastBilling->billing_date)->addMonths(6),
            MaintenanceContractBillingCycle::ANNUAL => \Carbon\Carbon::parse($lastBilling->billing_date)->addYear(),
            default => null,
        };
    }

    public function approve(int $userId): void
    {
        $this->update([
            'status' => MaintenanceContractStatus::ACTIVE,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);
    }

    public function terminate(string $reason): void
    {
        $this->update([
            'status' => MaintenanceContractStatus::TERMINATED,
            'special_terms' => $this->special_terms . "\n[TERMINATED: " . now()->format('Y-m-d') . "] " . $reason,
        ]);
    }

    public function renew(\DateTime $newEndDate, ?float $newValue = null): self
    {
        // Create renewal contract
        return self::create([
            'contract_no' => $this->contract_no . '-R' . now()->format('Y'),
            'description' => $this->description . ' (Renewal)',
            'contract_type' => $this->contract_type,
            'status' => MaintenanceContractStatus::DRAFT,
            'vendor_id' => $this->vendor_id,
            'start_date' => $this->end_date->copy()->addDay(),
            'end_date' => $newEndDate,
            'contract_value' => $newValue ?? $this->contract_value,
            'billing_cycle' => $this->billing_cycle,
            'billing_amount' => $this->billing_amount,
            'expense_account_id' => $this->expense_account_id,
            'created_by' => auth()->id(),
        ]);
    }
}
