<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollLine extends Model
{
    protected $fillable = [
        'payroll_document_id',
        'employee_id',
        'pay_code_id',
        'line_type',
        'amount',
        'hours',
        'rate',
        'employer_amount',
        'description',
        'posted_to_g_l',
        'posted_at',
        'gl_entry_id',
        'attendance_payroll_review_batch_line_id',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'hours' => 'decimal:2',
        'rate' => 'decimal:4',
        'employer_amount' => 'decimal:2',
        'posted_to_g_l' => 'boolean',
        'posted_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(PayrollDocument::class, 'payroll_document_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payCode(): BelongsTo
    {
        return $this->belongsTo(PayCode::class);
    }

    public function glEntry(): BelongsTo
    {
        return $this->belongsTo(GlEntry::class, 'gl_entry_id');
    }

    public function attendancePayrollReviewBatchLine(): BelongsTo
    {
        return $this->belongsTo(AttendancePayrollReviewBatchLine::class);
    }
}
