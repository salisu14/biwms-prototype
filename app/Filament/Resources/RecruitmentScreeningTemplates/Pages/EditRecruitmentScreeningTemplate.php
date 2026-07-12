<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentScreeningTemplates\Pages;

use App\Filament\Resources\RecruitmentScreeningTemplates\RecruitmentScreeningTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRecruitmentScreeningTemplate extends EditRecord
{
    protected static string $resource = RecruitmentScreeningTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
