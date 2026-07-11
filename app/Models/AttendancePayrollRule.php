<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePayrollRule extends Model
{
    public const IMPACT_EARNING = 'earning';

    public const IMPACT_DEDUCTION = 'deduction';

    public const IMPACT_INFORMATIONAL = 'informational';

    protected $fillable = [
        'business_id', 'code', 'name', 'impact_type', 'attendance_issue_type',
        'calculation_method', 'rate', 'minimum_minutes', 'maximum_minutes', 'rounding_rule',
        'earning_component_id', 'deduction_component_id', 'is_active', 'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'minimum_minutes' => 'integer',
        'maximum_minutes' => 'integer',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function earningComponent(): BelongsTo
    {
        return $this->belongsTo(PayCode::class, 'earning_component_id');
    }

    public function deductionComponent(): BelongsTo
    {
        return $this->belongsTo(PayCode::class, 'deduction_component_id');
    }
}
