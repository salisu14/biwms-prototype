<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentInterviewScorecardTemplates\Schemas;

use App\Models\RecruitmentInterviewScorecardTemplate;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentInterviewScorecardTemplateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return RecruitmentResourceSchema::infolist($schema, RecruitmentInterviewScorecardTemplate::class);
    }
}
