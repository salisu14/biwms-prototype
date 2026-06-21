<?php

namespace App\Filament\Service\Pages;

use App\Filament\Service\Widgets\DispatchBoardStatsOverview;
use App\Filament\Service\Widgets\DispatchCalendarWidget;
use App\Filament\Service\Widgets\OverdueDispatchesWidget;
use App\Filament\Service\Widgets\TechnicianWorkloadWidget;
use App\Models\Employee;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Panel;
use Filament\Schemas\Schema;

class DispatchBoard extends BaseDashboard
{
    use HasFiltersForm;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Dispatch Board';

    protected static ?string $title = 'Service Dispatch Board';

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public function getWidgets(): array
    {
        return [
            DispatchBoardStatsOverview::class,
            TechnicianWorkloadWidget::class,
            OverdueDispatchesWidget::class,
            DispatchCalendarWidget::class,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date_from')
                    ->label('Date From')
                    ->default(now()->toDateString()),
                DatePicker::make('date_to')
                    ->label('Date To')
                    ->default(now()->addDays(14)->toDateString()),
                Select::make('technician_id')
                    ->label('Technician')
                    ->options(
                        Employee::query()
                            ->where('is_active', true)
                            ->orderBy('first_name')
                            ->get()
                            ->mapWithKeys(fn (Employee $employee): array => [
                                (string) $employee->id => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')) ?: ('Employee #'.$employee->id),
                            ])
                            ->all()
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder('All Technicians'),
                Toggle::make('unassigned_only')
                    ->label('Unassigned only')
                    ->default(false),
            ]);
    }
}
