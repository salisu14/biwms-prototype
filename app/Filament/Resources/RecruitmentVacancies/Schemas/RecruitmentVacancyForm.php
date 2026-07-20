<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentVacancies\Schemas;

use App\Models\RecruitmentVacancy;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentVacancyForm
{
    public static function configure(Schema $schema): Schema
    {
        return RecruitmentResourceSchema::form($schema, RecruitmentVacancy::class);
    }
}
