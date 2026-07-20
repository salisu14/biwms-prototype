<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentScreeningTemplates\Schemas;

use App\Models\RecruitmentScreeningTemplate;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentScreeningTemplateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return RecruitmentResourceSchema::infolist($schema, RecruitmentScreeningTemplate::class);
    }
}
