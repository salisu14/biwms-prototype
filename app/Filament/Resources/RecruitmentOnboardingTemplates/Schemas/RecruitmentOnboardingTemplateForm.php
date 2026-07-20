<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOnboardingTemplates\Schemas;

use App\Models\RecruitmentOnboardingTemplate;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentOnboardingTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return RecruitmentResourceSchema::form($schema, RecruitmentOnboardingTemplate::class);
    }
}
