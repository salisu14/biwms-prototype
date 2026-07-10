<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeePayslip extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_GENERATED = 'generated';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'business_id',
        'employee_id',
        'payroll_period_id',
        'payroll_document_id',
        'payroll_line_id',
        'payslip_number',
        'verification_token',
        'status',
        'currency_code',
        'payment_date',
        'worked_days',
        'gross_earnings',
        'total_deductions',
        'net_pay',
        'amount_in_words',
        'employee_number',
        'employee_name',
        'employee_email',
        'employee_phone',
        'department_name',
        'job_title',
        'company_name',
        'company_address',
        'company_phone',
        'company_email',
        'company_logo_path',
        'generated_by',
        'generated_at',
        'download_count',
        'printed_at',
        'emailed_at',
        'revoked_at',
        'revoked_by',
        'revocation_reason',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'worked_days' => 'decimal:2',
        'gross_earnings' => 'decimal:4',
        'total_deductions' => 'decimal:4',
        'net_pay' => 'decimal:4',
        'generated_at' => 'datetime',
        'printed_at' => 'datetime',
        'emailed_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function payrollDocument(): BelongsTo
    {
        return $this->belongsTo(PayrollDocument::class);
    }

    public function payrollLine(): BelongsTo
    {
        return $this->belongsTo(PayrollLine::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(EmployeePayslipEarning::class, 'payslip_id')->orderBy('display_order');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(EmployeePayslipDeduction::class, 'payslip_id')->orderBy('display_order');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(EmployeePayslipHistory::class, 'payslip_id')->latest('occurred_at');
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }
}
