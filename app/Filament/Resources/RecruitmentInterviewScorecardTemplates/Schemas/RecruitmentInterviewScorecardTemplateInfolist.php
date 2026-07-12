<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentInterviewScorecardTemplates\Schemas;

use App\Models\RecruitmentInterviewScorecardTemplate;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentInterviewScorecardTemplateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::infolist($schema, RecruitmentInterviewScorecardTemplate::class);
    }
}
