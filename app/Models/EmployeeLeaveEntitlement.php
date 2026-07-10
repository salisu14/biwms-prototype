<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeaveEntitlement extends Model
{
    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'leave_policy_id',
        'leave_year',
        'opening_balance',
        'entitled_amount',
        'carried_forward',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'leave_year' => 'integer',
        'opening_balance' => 'decimal:2',
        'entitled_amount' => 'decimal:2',
        'carried_forward' => 'decimal:2',
        'expires_at' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class, 'leave_policy_id');
    }
}
