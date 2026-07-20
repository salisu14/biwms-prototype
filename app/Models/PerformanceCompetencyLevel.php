<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceCompetencyLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'performance_competency_id',
        'level_number',
        'name',
        'description',
        'behavioural_indicators',
    ];

    protected function casts(): array
    {
        return [
            'level_number' => 'integer',
            'behavioural_indicators' => 'array',
        ];
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(
            PerformanceCompetency::class,
            'performance_competency_id'
        );
    }
}
