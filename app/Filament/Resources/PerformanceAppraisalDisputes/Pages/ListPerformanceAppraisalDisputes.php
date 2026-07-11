<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalDisputes\Pages;

use App\Filament\Resources\PerformanceAppraisalDisputes\PerformanceAppraisalDisputeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPerformanceAppraisalDisputes extends ListRecords
{
    protected static string $resource = PerformanceAppraisalDisputeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
