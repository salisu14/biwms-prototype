<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkforceRosterRole extends Model
{
    protected $fillable = [
        'business_id', 'code', 'name', 'description', 'department_id',
        'work_center_id', 'is_critical', 'is_active',
    ];

    protected $casts = [
        'is_critical' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
