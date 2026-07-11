<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalTemplates\Pages;

use App\Filament\Resources\PerformanceAppraisalTemplates\PerformanceAppraisalTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPerformanceAppraisalTemplate extends EditRecord
{
    protected static string $resource = PerformanceAppraisalTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
