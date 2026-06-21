<?php

namespace App\Filament\Service\Widgets;

use App\Models\MaintenanceContractSchedule;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;

class OverdueDispatchesWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    protected static ?string $heading = 'Overdue Dispatches';

    public function table(Table $table): Table
    {
        $dateFrom = $this->filters['date_from'] ?? now()->toDateString();
        $dateTo = $this->filters['date_to'] ?? now()->addDays(14)->toDateString();
        $technicianId = $this->filters['technician_id'] ?? null;

        return $table
            ->query(
                MaintenanceContractSchedule::query()
                    ->with(['maintenanceContract.responsibleEmployee', 'fixedAsset'])
                    ->where('is_active', true)
                    ->whereBetween('next_service_date', [$dateFrom, $dateTo])
                    ->whereDate('next_service_date', '<=', now()->toDateString())
                    ->when($technicianId, fn ($query) => $query->whereHas('maintenanceContract', fn ($contractQuery) => $contractQuery->where('responsible_employee_id', $technicianId)))
                    ->orderBy('next_service_date')
                    ->limit(15)
            )
            ->columns([
                Tables\Columns\TextColumn::make('maintenanceContract.contract_no')
                    ->label('Contract')
                    ->searchable(),
                Tables\Columns\TextColumn::make('fixedAsset.description')
                    ->label('Asset')
                    ->searchable(),
                Tables\Columns\TextColumn::make('service_description')
                    ->limit(40),
                Tables\Columns\TextColumn::make('maintenanceContract.responsibleEmployee.first_name')
                    ->label('Technician')
                    ->formatStateUsing(fn ($state, $record) => trim(($record->maintenanceContract?->responsibleEmployee?->first_name ?? '').' '.($record->maintenanceContract?->responsibleEmployee?->last_name ?? '')))
                    ->placeholder('Unassigned'),
                Tables\Columns\TextColumn::make('next_service_date')
                    ->date()
                    ->color('danger'),
            ])
            ->recordActions([
                Action::make('complete_now')
                    ->label('Complete')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (MaintenanceContractSchedule $record): void {
                        $record->completeService(now());
                        Notification::make()->title('Dispatch completed')->success()->send();
                    }),
                Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-m-eye')
                    ->url(fn (MaintenanceContractSchedule $record): string => route('filament.service.resources.service-dispatches.edit', $record)),
            ]);
    }
}
