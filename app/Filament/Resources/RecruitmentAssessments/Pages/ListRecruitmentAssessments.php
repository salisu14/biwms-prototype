<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentAssessments\Pages;

use App\Filament\Resources\RecruitmentAssessments\RecruitmentAssessmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecruitmentAssessments extends ListRecords
{
    protected static string $resource = RecruitmentAssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
