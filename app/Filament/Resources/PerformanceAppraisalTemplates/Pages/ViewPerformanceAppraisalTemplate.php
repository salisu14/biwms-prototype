<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalTemplates\Pages;

use App\Filament\Resources\PerformanceAppraisalTemplates\PerformanceAppraisalTemplateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPerformanceAppraisalTemplate extends ViewRecord
{
    protected static string $resource = PerformanceAppraisalTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
