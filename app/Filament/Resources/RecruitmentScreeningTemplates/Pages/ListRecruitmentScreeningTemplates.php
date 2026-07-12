<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentScreeningTemplates\Pages;

use App\Filament\Resources\RecruitmentScreeningTemplates\RecruitmentScreeningTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecruitmentScreeningTemplates extends ListRecords
{
    protected static string $resource = RecruitmentScreeningTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
