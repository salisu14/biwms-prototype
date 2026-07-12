<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentVacancies\Pages;

use App\Filament\Resources\RecruitmentVacancies\RecruitmentVacancyResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRecruitmentVacancy extends EditRecord
{
    protected static string $resource = RecruitmentVacancyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
