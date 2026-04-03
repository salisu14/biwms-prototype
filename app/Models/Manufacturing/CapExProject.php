<?php

namespace App\Models\Manufacturing;

use App\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CapExProject extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_number',
        'description',
        'status',
        'fixed_asset_id',
        'budget_amount',
        'committed_amount',
        'actual_amount',
        'capitalized_amount',
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'capitalize_labor',
        'capitalize_materials',
        'capitalize_overhead',
        'capitalize_interest',
        'capitalization_threshold',
        'wip_gl_account_id',
        'capex_gl_account_id',
        'interest_capitalization_rate',
        'capitalized_interest_to_date',
        'project_manager_id',
        'approver_id',
        'approved_at',
        'created_by',
        'last_modified_by',
    ];

    protected $casts = [
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
        'approved_at' => 'datetime',
        'budget_amount' => 'decimal:2',
        'committed_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'capitalized_amount' => 'decimal:2',
        'capitalization_threshold' => 'decimal:2',
        'interest_capitalization_rate' => 'decimal:2',
        'capitalized_interest_to_date' => 'decimal:2',
        'capitalize_labor' => 'boolean',
        'capitalize_materials' => 'boolean',
        'capitalize_overhead' => 'boolean',
        'capitalize_interest' => 'boolean',
    ];

    protected $table = 'capex_projects';

    // Relationships

    public function lines(): HasMany
    {
        return $this->hasMany(CapExProjectLine::class);
    }

    public function fixedAsset(): HasOne
    {
        return $this->hasOne(FixedAsset::class);
    }

    public function targetAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    /**
     * Relationship for WIP G/L Account
     */
    public function wipAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'wip_gl_account_id');
    }

    /**
     * Relationship for CapEx G/L Account
     */
    public function capexAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'capex_gl_account_id');
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'project_manager_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approver_id');
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
        return $query->whereIn('status', ['APPROVED', 'IN_PROGRESS', 'ON_HOLD']);
    }

    public function scopePendingApproval($query)
    {
        return $query->where('status', 'PENDING_APPROVAL');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'COMPLETED');
    }

    public function scopeOverBudget($query)
    {
        return $query->whereRaw('actual_amount > budget_amount');
    }

    // Business Logic

    /**
     * Get next line number for new project line
     */
    public function getNextLineNumber(): int
    {
        return ($this->lines()->max('line_number') ?? 0) + 10000;
    }

    /**
     * Calculate total eligible for capitalization
     */
    public function getEligibleForCapitalization(): float
    {
        return $this->lines()
            ->where('eligible_for_capitalization', true)
            ->where('capitalized', false)
            ->sum('actual_amount');
    }

    /**
     * Calculate variance (budget vs actual)
     */
    public function getVariance(): float
    {
        return $this->actual_amount - $this->budget_amount;
    }

    /**
     * Get variance percentage
     */
    public function getVariancePercent(): float
    {
        return $this->budget_amount > 0
            ? ($this->getVariance() / $this->budget_amount) * 100
            : 0;
    }

    /**
     * Check if project is over budget
     */
    public function isOverBudget(): bool
    {
        return $this->actual_amount > $this->budget_amount;
    }

    /**
     * Get completion percentage
     */
    public function getCompletionPercent(): float
    {
        if ($this->budget_amount <= 0) {
            return 0;
        }

        return min(100, ($this->actual_amount / $this->budget_amount) * 100);
    }

    /**
     * Approve project
     */
    public function approve(int $approverId): void
    {
        $this->update([
            'status' => 'APPROVED',
            'approver_id' => $approverId,
            'approved_at' => now(),
        ]);
    }

    /**
     * Start project (transition to IN_PROGRESS)
     */
    public function start(): void
    {
        $this->update([
            'status' => 'IN_PROGRESS',
            'actual_start_date' => now(),
        ]);
    }

    /**
     * Calculate interest to capitalize for period (avoided cost method)
     */
    public function calculateCapitalizableInterest(\DateTime $periodStart, \DateTime $periodEnd): float
    {
        if (!$this->capitalize_interest || !$this->interest_capitalization_rate) {
            return 0;
        }

        $daysInPeriod = $periodStart->diffInDays($periodEnd);
        $averageExpenditures = $this->getAverageAccumulatedExpenditures($periodStart, $periodEnd);

        // Avoided cost = Average accumulated expenditures × Interest rate × (Days/365)
        $interest = $averageExpenditures * ($this->interest_capitalization_rate / 100) * ($daysInPeriod / 365);

        // Cap at actual interest incurred (simplified - in practice, compare to actual interest expense)
        return round($interest, 2);
    }

    /**
     * Get weighted average accumulated expenditures for interest calculation
     */
    protected function getAverageAccumulatedExpenditures(\DateTime $periodStart, \DateTime $periodEnd): float
    {
        // Simplified: Use actual amount at mid-period
        // Full implementation would weight each expenditure by time outstanding
        return $this->lines()
                ->where('capitalized', true)
                ->whereBetween('capitalized_at', [$periodStart, $periodEnd])
                ->sum('actual_amount') / 2;
    }

    /**
     * Update actual amount from lines
     */
    public function recalculateActualAmount(): void
    {
        $this->update([
            'actual_amount' => $this->lines()->sum('actual_amount'),
        ]);
    }

    /**
     * Check if ready for capitalization
     */
    public function isReadyForCapitalization(): bool
    {
        return in_array($this->status, ['IN_PROGRESS', 'ON_HOLD'])
            && $this->getEligibleForCapitalization() > 0
            && $this->lines()->where('eligible_for_capitalization', true)->where('capitalized', false)->exists();
    }

    protected static function booted(): void
    {
        static::creating(function ($capex) {
            if (auth()->check()) {
                $capex->approver_id = auth()->id();
                $capex->created_by = auth()->id();
                $capex->last_modified_by = auth()->id();
            }
        });

        static::updating(function ($capex) {
            if (auth()->check()) {
                $capex->last_modified_by = auth()->id();
            }
        });
    }
}
