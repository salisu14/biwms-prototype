<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentAssessments\Schemas;

use App\Models\RecruitmentAssessment;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentAssessmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::form($schema, RecruitmentAssessment::class);
    }
}
