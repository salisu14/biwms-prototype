<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalModerationSessions\Pages;

use App\Filament\Resources\PerformanceAppraisalModerationSessions\PerformanceAppraisalModerationSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPerformanceAppraisalModerationSessions extends ListRecords
{
    protected static string $resource = PerformanceAppraisalModerationSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
