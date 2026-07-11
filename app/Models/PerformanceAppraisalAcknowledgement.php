<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceAppraisalAcknowledgement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'acknowledged_at' => 'datetime',
    ];
}
