<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalDisputes\Pages;

use App\Filament\Resources\PerformanceAppraisalDisputes\PerformanceAppraisalDisputeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPerformanceAppraisalDispute extends EditRecord
{
    protected static string $resource = PerformanceAppraisalDisputeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
