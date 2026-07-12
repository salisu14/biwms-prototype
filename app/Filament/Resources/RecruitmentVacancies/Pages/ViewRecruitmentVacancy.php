<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentVacancies\Pages;

use App\Filament\Resources\RecruitmentVacancies\RecruitmentVacancyResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRecruitmentVacancy extends ViewRecord
{
    protected static string $resource = RecruitmentVacancyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
