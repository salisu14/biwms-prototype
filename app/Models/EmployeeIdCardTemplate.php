<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeIdCardTemplate extends Model
{
    protected $fillable = [
        'business_id',
        'name',
        'orientation',
        'width_mm',
        'height_mm',
        'colors',
        'placement_presets',
        'visible_fields',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'colors' => 'array',
        'placement_presets' => 'array',
        'visible_fields' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'width_mm' => 'decimal:2',
        'height_mm' => 'decimal:2',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(EmployeeIdCard::class, 'template_id');
    }
}
