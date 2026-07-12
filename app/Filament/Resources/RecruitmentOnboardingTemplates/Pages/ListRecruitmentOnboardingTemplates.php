<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOnboardingTemplates\Pages;

use App\Filament\Resources\RecruitmentOnboardingTemplates\RecruitmentOnboardingTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecruitmentOnboardingTemplates extends ListRecords
{
    protected static string $resource = RecruitmentOnboardingTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
