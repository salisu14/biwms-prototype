<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentVacancies\Pages;

use App\Filament\Resources\RecruitmentVacancies\RecruitmentVacancyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecruitmentVacancies extends ListRecords
{
    protected static string $resource = RecruitmentVacancyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
