<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentInterviewScorecardTemplates\Pages;

use App\Filament\Resources\RecruitmentInterviewScorecardTemplates\RecruitmentInterviewScorecardTemplateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRecruitmentInterviewScorecardTemplate extends ViewRecord
{
    protected static string $resource = RecruitmentInterviewScorecardTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
