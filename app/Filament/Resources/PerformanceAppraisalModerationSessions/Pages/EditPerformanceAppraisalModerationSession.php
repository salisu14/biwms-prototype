<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalModerationSessions\Pages;

use App\Filament\Resources\PerformanceAppraisalModerationSessions\PerformanceAppraisalModerationSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPerformanceAppraisalModerationSession extends EditRecord
{
    protected static string $resource = PerformanceAppraisalModerationSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
