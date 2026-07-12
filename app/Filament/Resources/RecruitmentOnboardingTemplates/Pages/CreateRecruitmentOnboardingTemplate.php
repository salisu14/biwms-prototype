<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOnboardingTemplates\Pages;

use App\Filament\Resources\RecruitmentOnboardingTemplates\RecruitmentOnboardingTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecruitmentOnboardingTemplate extends CreateRecord
{
    protected static string $resource = RecruitmentOnboardingTemplateResource::class;
}
