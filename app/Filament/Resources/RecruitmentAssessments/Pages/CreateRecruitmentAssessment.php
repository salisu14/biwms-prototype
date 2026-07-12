<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentAssessments\Pages;

use App\Filament\Resources\RecruitmentAssessments\RecruitmentAssessmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecruitmentAssessment extends CreateRecord
{
    protected static string $resource = RecruitmentAssessmentResource::class;
}
