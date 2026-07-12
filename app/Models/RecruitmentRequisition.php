<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecruitmentRequisition extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_OPEN = 'open';

    public const STATUS_FILLED = 'filled';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_CLOSED = 'closed';

    protected $guarded = [];

    protected $casts = [
        'requested_start_date' => 'date',
        'budgeted_salary_min' => 'decimal:4',
        'budgeted_salary_max' => 'decimal:4',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (RecruitmentRequisition $requisition): void {
            if ((int) $requisition->requested_headcount <= 0) {
                throw new \RuntimeException('Requested headcount must be greater than zero.');
            }

            if ($requisition->budgeted_salary_min !== null
                && $requisition->budgeted_salary_max !== null
                && (float) $requisition->budgeted_salary_max < (float) $requisition->budgeted_salary_min) {
                throw new \RuntimeException('Budgeted salary maximum cannot be below minimum.');
            }

            if ($requisition->requisition_type === 'replacement' && blank($requisition->replacement_for_employee_id)) {
                throw new \RuntimeException('Replacement requisitions must identify the employee being replaced.');
            }

            if ($requisition->exists
                && $requisition->getOriginal('status') === self::STATUS_APPROVED
                && $requisition->isDirty('requested_headcount')
                && (int) $requisition->requested_headcount > (int) $requisition->getOriginal('requested_headcount')) {
                throw new \RuntimeException('Approved headcount cannot be silently increased.');
            }
        });
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function hiringManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hiring_manager_employee_id');
    }

    public function recruiter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'recruiter_employee_id');
    }

    public function vacancies(): HasMany
    {
        return $this->hasMany(RecruitmentVacancy::class);
    }

    public function approvedHeadcountRemaining(): int
    {
        return max(0, (int) $this->requested_headcount - (int) $this->vacancies()->sum('number_of_openings'));
    }

    public function filledHeadcount(): int
    {
        return (int) $this->vacancies()->sum('filled_openings');
    }
}
