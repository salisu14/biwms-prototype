<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalCycles\Pages;

use App\Filament\Resources\PerformanceAppraisalCycles\PerformanceAppraisalCycleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPerformanceAppraisalCycle extends EditRecord
{
    protected static string $resource = PerformanceAppraisalCycleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
