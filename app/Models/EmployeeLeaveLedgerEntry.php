<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeaveLedgerEntry extends Model
{
    public const TYPE_OPENING = 'opening';

    public const TYPE_ENTITLEMENT = 'entitlement';

    public const TYPE_ACCRUAL = 'accrual';

    public const TYPE_APPROVED_LEAVE = 'approved_leave';

    public const TYPE_REVERSAL = 'reversal';

    public const TYPE_CARRY_FORWARD = 'carry_forward';

    public const TYPE_EXPIRY = 'expiry';

    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'leave_request_id',
        'leave_year',
        'entry_type',
        'quantity',
        'posting_date',
        'description',
        'reference_type',
        'reference_id',
        'created_by',
    ];

    protected $casts = [
        'leave_year' => 'integer',
        'quantity' => 'decimal:2',
        'posting_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
