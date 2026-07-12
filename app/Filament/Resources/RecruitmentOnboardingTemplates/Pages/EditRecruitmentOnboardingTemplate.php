<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOnboardingTemplates\Pages;

use App\Filament\Resources\RecruitmentOnboardingTemplates\RecruitmentOnboardingTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRecruitmentOnboardingTemplate extends EditRecord
{
    protected static string $resource = RecruitmentOnboardingTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
