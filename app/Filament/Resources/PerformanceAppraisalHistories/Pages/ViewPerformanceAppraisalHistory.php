<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalHistories\Pages;

use App\Filament\Resources\PerformanceAppraisalHistories\PerformanceAppraisalHistoryResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPerformanceAppraisalHistory extends ViewRecord
{
    protected static string $resource = PerformanceAppraisalHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
