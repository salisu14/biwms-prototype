<?php

namespace App\Models;

use App\Contracts\Approvable;
use App\Enums\PayrollStatus;
use App\Traits\Approvable as ApprovableTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollDocument extends Model implements Approvable
{
    use ApprovableTrait;

    protected $fillable = [
        'document_number',
        'payroll_period_id',
        'period_start',
        'period_end',
        'status',
        'remarks',
        'total_earnings',
        'total_deductions',
        'total_net_pay',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'status' => PayrollStatus::class,
        'total_earnings' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net_pay' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    public function getApprovalAmount(): float
    {
        return (float) ($this->total_net_pay ?? 0);
    }

    public function getApprovalDocumentType(): string
    {
        return 'Payroll Document';
    }

    public function getApprovalRequestorId(): int
    {
        return (int) auth()->id();
    }

    public function markAsReleased(): void
    {
        $this->update([
            'status' => PayrollStatus::APPROVED,
            'approved_at' => now(),
        ]);
    }
}
