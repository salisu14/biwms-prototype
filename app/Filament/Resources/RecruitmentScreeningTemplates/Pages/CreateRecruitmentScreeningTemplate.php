<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentScreeningTemplates\Pages;

use App\Filament\Resources\RecruitmentScreeningTemplates\RecruitmentScreeningTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecruitmentScreeningTemplate extends CreateRecord
{
    protected static string $resource = RecruitmentScreeningTemplateResource::class;
}
