<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceDevelopmentAction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'target_date' => 'date',
        'completed_at' => 'datetime',
    ];
}
