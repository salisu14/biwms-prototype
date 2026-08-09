<?php

declare(strict_types=1);

namespace App\Filament\Resources\Manufacturing\ProductionSchedules\Pages;

use App\Enums\ProductionSchedulingMode;
use App\Filament\Resources\Manufacturing\ProductionSchedules\ProductionScheduleResource;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionSchedule;
use App\Services\Manufacturing\ProductionSchedulingService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListProductionSchedules extends ListRecords
{
    protected static string $resource = ProductionScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateSchedule')
                ->label('Generate Schedule')
                ->icon('heroicon-o-sparkles')
                ->authorize(fn (): bool => auth()->user()?->can('generate', ProductionSchedule::class) ?? false)
                ->form([
                    Select::make('production_order_ids')
                        ->label('Production Orders')
                        ->multiple()
                        ->options(fn () => ProductionOrder::query()->orderBy('document_number')->pluck('document_number', 'id')->all())
                        ->searchable()
                        ->required(),
                    Select::make('mode')
                        ->options([
                            ProductionSchedulingMode::Forward->value => 'Forward',
                            ProductionSchedulingMode::Backward->value => 'Backward',
                        ])
                        ->default(ProductionSchedulingMode::Forward->value)
                        ->required(),
                    DateTimePicker::make('horizon_start_at')
                        ->default(now()->startOfHour())
                        ->required()
                        ->seconds(false),
                    DateTimePicker::make('horizon_end_at')
                        ->default(now()->addWeek()->endOfHour())
                        ->required()
                        ->seconds(false),
                ])
                ->action(function (array $data): void {
                    $result = app(ProductionSchedulingService::class)->generate([
                        'production_order_ids' => array_map('intval', $data['production_order_ids'] ?? []),
                        'mode' => $data['mode'],
                        'horizon_start_at' => $data['horizon_start_at'],
                        'horizon_end_at' => $data['horizon_end_at'],
                        'generated_by' => auth()->id(),
                    ]);

                    Notification::make()
                        ->title('Schedule generated')
                        ->body("{$result->summary['operations_scheduled']} operation(s), {$result->summary['exceptions']} exception(s).")
                        ->success()
                        ->send();
                }),
        ];
    }
}
