<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceGoals\Pages;

use App\Filament\Resources\PerformanceGoals\PerformanceGoalResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPerformanceGoal extends EditRecord
{
    protected static string $resource = PerformanceGoalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
