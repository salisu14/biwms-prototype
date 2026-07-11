<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalModerationSessions\Pages;

use App\Filament\Resources\PerformanceAppraisalModerationSessions\PerformanceAppraisalModerationSessionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPerformanceAppraisalModerationSession extends ViewRecord
{
    protected static string $resource = PerformanceAppraisalModerationSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
