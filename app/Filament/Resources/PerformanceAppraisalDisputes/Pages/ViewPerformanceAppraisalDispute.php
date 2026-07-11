<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalDisputes\Pages;

use App\Filament\Resources\PerformanceAppraisalDisputes\PerformanceAppraisalDisputeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPerformanceAppraisalDispute extends ViewRecord
{
    protected static string $resource = PerformanceAppraisalDisputeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
