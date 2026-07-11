<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalCycles\Pages;

use App\Filament\Resources\PerformanceAppraisalCycles\PerformanceAppraisalCycleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPerformanceAppraisalCycle extends ViewRecord
{
    protected static string $resource = PerformanceAppraisalCycleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
