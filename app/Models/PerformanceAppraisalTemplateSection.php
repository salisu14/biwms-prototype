<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceAppraisalTemplateSection extends Model
{
    protected $guarded = [];

    protected $casts = [
        'weight_percent' => 'decimal:4',
        'sort_order' => 'integer',
        'is_required' => 'boolean',
        'allow_employee_rating' => 'boolean',
        'allow_manager_rating' => 'boolean',
        'allow_comment' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PerformanceAppraisalTemplateItem::class)->orderBy('sort_order');
    }
}
