<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalHistories\Pages;

use App\Filament\Resources\PerformanceAppraisalHistories\PerformanceAppraisalHistoryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPerformanceAppraisalHistory extends EditRecord
{
    protected static string $resource = PerformanceAppraisalHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
