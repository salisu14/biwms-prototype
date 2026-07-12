<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentInterviewScorecardTemplates\Pages;

use App\Filament\Resources\RecruitmentInterviewScorecardTemplates\RecruitmentInterviewScorecardTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRecruitmentInterviewScorecardTemplate extends EditRecord
{
    protected static string $resource = RecruitmentInterviewScorecardTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
