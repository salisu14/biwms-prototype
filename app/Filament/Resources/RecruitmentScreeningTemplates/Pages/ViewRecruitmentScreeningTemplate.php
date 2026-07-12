<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentScreeningTemplates\Pages;

use App\Filament\Resources\RecruitmentScreeningTemplates\RecruitmentScreeningTemplateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRecruitmentScreeningTemplate extends ViewRecord
{
    protected static string $resource = RecruitmentScreeningTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
