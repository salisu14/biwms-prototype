<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisals\Pages;

use App\Filament\Resources\PerformanceAppraisals\PerformanceAppraisalResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPerformanceAppraisal extends ViewRecord
{
    protected static string $resource = PerformanceAppraisalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
