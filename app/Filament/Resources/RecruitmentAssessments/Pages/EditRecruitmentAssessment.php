<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentAssessments\Pages;

use App\Filament\Resources\RecruitmentAssessments\RecruitmentAssessmentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRecruitmentAssessment extends EditRecord
{
    protected static string $resource = RecruitmentAssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
