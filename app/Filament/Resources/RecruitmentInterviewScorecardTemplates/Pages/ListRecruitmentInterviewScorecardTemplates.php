<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentInterviewScorecardTemplates\Pages;

use App\Filament\Resources\RecruitmentInterviewScorecardTemplates\RecruitmentInterviewScorecardTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecruitmentInterviewScorecardTemplates extends ListRecords
{
    protected static string $resource = RecruitmentInterviewScorecardTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
