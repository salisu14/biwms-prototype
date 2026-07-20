<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentVacancies\Schemas;

use App\Models\RecruitmentVacancy;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentVacancyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return RecruitmentResourceSchema::infolist($schema, RecruitmentVacancy::class);
    }
}
