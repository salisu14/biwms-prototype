<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOnboardingTemplates\Pages;

use App\Filament\Resources\RecruitmentOnboardingTemplates\RecruitmentOnboardingTemplateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRecruitmentOnboardingTemplate extends ViewRecord
{
    protected static string $resource = RecruitmentOnboardingTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
