<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitmentInterviewPanelMember extends Model
{
    protected $guarded = [];

    protected $casts = [
        'can_score' => 'boolean',
        'can_comment' => 'boolean',
        'is_confidential' => 'boolean',
    ];
}
