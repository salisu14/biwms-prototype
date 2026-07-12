<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentVacancies\Pages;

use App\Filament\Resources\RecruitmentVacancies\RecruitmentVacancyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecruitmentVacancy extends CreateRecord
{
    protected static string $resource = RecruitmentVacancyResource::class;
}
