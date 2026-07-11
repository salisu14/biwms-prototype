<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceCompetencies\Pages;

use App\Filament\Resources\PerformanceCompetencies\PerformanceCompetencyResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPerformanceCompetency extends EditRecord
{
    protected static string $resource = PerformanceCompetencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
