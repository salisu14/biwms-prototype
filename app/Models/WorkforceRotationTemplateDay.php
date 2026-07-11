<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkforceRotationTemplateDay extends Model
{
    protected $fillable = [
        'workforce_rotation_template_id', 'sequence_day', 'employee_shift_id',
        'is_rest_day', 'attendance_location_id', 'work_center_id', 'roster_role_id', 'notes',
    ];

    protected $casts = [
        'sequence_day' => 'integer',
        'is_rest_day' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkforceRotationTemplate::class, 'workforce_rotation_template_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(EmployeeShift::class, 'employee_shift_id');
    }
}
