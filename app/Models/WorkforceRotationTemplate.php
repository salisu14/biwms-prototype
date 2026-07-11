<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkforceRotationTemplate extends Model
{
    protected $fillable = [
        'business_id', 'code', 'name', 'description', 'cycle_length_days',
        'is_active', 'effective_from', 'effective_to',
    ];

    protected $casts = [
        'cycle_length_days' => 'integer',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function days(): HasMany
    {
        return $this->hasMany(WorkforceRotationTemplateDay::class);
    }
}
