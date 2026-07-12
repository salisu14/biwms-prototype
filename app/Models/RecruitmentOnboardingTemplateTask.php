<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitmentOnboardingTemplateTask extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_required' => 'boolean',
        'requires_attachment' => 'boolean',
        'requires_approval' => 'boolean',
    ];
}
