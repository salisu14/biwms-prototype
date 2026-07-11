<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalCycles\Pages;

use App\Filament\Resources\PerformanceAppraisalCycles\PerformanceAppraisalCycleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPerformanceAppraisalCycles extends ListRecords
{
    protected static string $resource = PerformanceAppraisalCycleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
