<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentAssessments\Pages;

use App\Filament\Resources\RecruitmentAssessments\RecruitmentAssessmentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRecruitmentAssessment extends ViewRecord
{
    protected static string $resource = RecruitmentAssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
