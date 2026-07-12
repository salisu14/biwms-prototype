<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitmentJobPosting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'published_at' => 'datetime',
        'closes_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];
}
