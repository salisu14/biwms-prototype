<?php

declare(strict_types=1);

namespace App\Filament\Resources\Manufacturing\ProductionSchedules\Pages;

use App\Filament\Resources\Manufacturing\ProductionSchedules\ProductionScheduleResource;
use App\Services\Manufacturing\ProductionSchedulingService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewProductionSchedule extends ViewRecord
{
    protected static string $resource = ProductionScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->authorize(fn ($record): bool => auth()->user()?->can('approve', $record) ?? false)
                ->visible(fn ($record): bool => in_array($record->status?->value, ['generated', 'reviewed'], true))
                ->action(fn ($record) => app(ProductionSchedulingService::class)->approve($record, auth()->id())),
            Action::make('reschedule')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->authorize(fn ($record): bool => auth()->user()?->can('reschedule', $record) ?? false)
                ->action(fn ($record) => app(ProductionSchedulingService::class)->reschedule($record, [
                    'reason' => 'planner_requested',
                    'generated_by' => auth()->id(),
                ])),
        ];
    }
}
