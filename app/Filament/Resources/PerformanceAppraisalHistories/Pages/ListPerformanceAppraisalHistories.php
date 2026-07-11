<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalHistories\Pages;

use App\Filament\Resources\PerformanceAppraisalHistories\PerformanceAppraisalHistoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPerformanceAppraisalHistories extends ListRecords
{
    protected static string $resource = PerformanceAppraisalHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
