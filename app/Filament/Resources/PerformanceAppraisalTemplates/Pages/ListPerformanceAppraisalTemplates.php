<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalTemplates\Pages;

use App\Filament\Resources\PerformanceAppraisalTemplates\PerformanceAppraisalTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPerformanceAppraisalTemplates extends ListRecords
{
    protected static string $resource = PerformanceAppraisalTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
