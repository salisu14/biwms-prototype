<?php

namespace App\Models;

use App\Contracts\Approvable;
use App\Enums\PayrollStatus;
use App\Services\NumberSeriesService;
use App\Traits\Approvable as ApprovableTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollDocument extends Model implements Approvable
{
    use ApprovableTrait;

    protected static function booted(): void
    {
        static::creating(function (PayrollDocument $document): void {
            if (empty($document->document_number)) {
                $document->document_number = self::generateDocumentNumber();
            }
        });
    }

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

    public static function generateDocumentNumber(): string
    {
        $seriesService = app(NumberSeriesService::class);

        foreach (['PAYROLL', 'PAYROLL_DOCUMENT', 'PRL'] as $seriesCode) {
            $nextNumber = $seriesService->tryGetNextNo($seriesCode);

            if (! empty($nextNumber)) {
                return $nextNumber;
            }
        }

        $periodKey = now()->format('Ym');
        $sequence = static::whereYear('created_at', now()->year)->count() + 1;

        return sprintf('PRL-%s-%04d', $periodKey, $sequence);
    }
}
