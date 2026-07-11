<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceCompetencyFrameworks\Pages;

use App\Filament\Resources\PerformanceCompetencyFrameworks\PerformanceCompetencyFrameworkResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPerformanceCompetencyFramework extends EditRecord
{
    protected static string $resource = PerformanceCompetencyFrameworkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
