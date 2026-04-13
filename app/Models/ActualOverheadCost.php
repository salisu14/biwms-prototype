<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Manufacturing\MachineCenter;
use App\Models\Manufacturing\WorkCenter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class ActualOverheadCost extends Model
{
    use HasFactory;

    protected $table = 'actual_overhead_costs';

    protected $fillable = [
        'work_center_id',
        'machine_center_id',
        'location_id',
        'period',
        'fiscal_year',
        'period_no',
        'cost_type',
        'cost_type_code',
        'amount',
        'allocated_amount',
        'gl_account_id',
        'gl_account_no',
        'document_type',
        'document_no',
        'document_date',
        'description',
        'notes',
        'status',
        'variance_journal_batch_id',
        'variance_posted_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'period' => 'date',
        'document_date' => 'date',
        'variance_posted_at' => 'datetime',
        'amount' => 'decimal:4',
        'allocated_amount' => 'decimal:4',
    ];

    // Relationships
    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function machineCenter(): BelongsTo
    {
        return $this->belongsTo(MachineCenter::class, 'machine_center_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(LocationMaster::class, 'location_id');
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'gl_account_id');
    }

    public function varianceJournalBatch(): BelongsTo
    {
        return $this->belongsTo(GeneralJournalBatch::class, 'variance_journal_batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeForPeriod($query, \DateTime $period)
    {
        return $query->where('period', $period->format('Y-m-01'));
    }

    public function scopeForWorkCenter($query, int $workCenterId)
    {
        return $query->where('work_center_id', $workCenterId);
    }

    public function scopeUnallocated($query)
    {
        return $query->where('status', 'unallocated');
    }

    public function scopePendingVariance($query)
    {
        return $query->whereIn('status', ['unallocated', 'partial']);
    }

    // Accessors
    public function getRemainingAmountAttribute(): float
    {
        return (float) $this->amount - (float) $this->allocated_amount;
    }

    public function getAllocationPercentageAttribute(): float
    {
        if ($this->amount == 0) {
            return 0;
        }

        return ((float) $this->allocated_amount / (float) $this->amount) * 100;
    }

    // Status checks
    public function isFullyAllocated(): bool
    {
        return $this->remaining_amount <= 0.01; // Tolerance for rounding
    }

    public function isVariancePosted(): bool
    {
        return $this->status === 'variance_posted';
    }

    // Business methods
    public function allocate(float $amount): void
    {
        $newAllocated = min(
            (float) $this->allocated_amount + $amount,
            (float) $this->amount
        );

        $this->update([
            'allocated_amount' => $newAllocated,
            'status' => $newAllocated >= $this->amount ? 'fully_allocated' : 'partial',
        ]);
    }

    public function markVariancePosted(int $journalBatchId): void
    {
        $this->update([
            'status' => 'variance_posted',
            'variance_journal_batch_id' => $journalBatchId,
            'variance_posted_at' => now(),
        ]);
    }

    // Static methods for aggregation
    public static function getTotalForWorkCenterAndPeriod(
        int $workCenterId,
        \DateTime $period
    ): float {
        return (float) self::where('work_center_id', $workCenterId)
            ->where('period', $period->format('Y-m-01'))
            ->sum('amount');
    }

    public static function getByCostType(
        int $workCenterId,
        \DateTime $period
    ): Collection {
        return self::where('work_center_id', $workCenterId)
            ->where('period', $period->format('Y-m-01'))
            ->select('cost_type', 'cost_type_code')
            ->selectRaw('SUM(amount) as total_amount')
            ->selectRaw('SUM(allocated_amount) as total_allocated')
            ->groupBy('cost_type', 'cost_type_code')
            ->get();
    }
}
