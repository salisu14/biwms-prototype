<?php

namespace App\Filament\Pages\Finance;

use App\Models\AccountSchedule;
use App\Services\IncomeStatementService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class ProfitAndLossReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static \UnitEnum|string|null $navigationGroup = 'Finance';
    protected static ?string $title = 'Profit & Loss Report';
    protected string $view = 'filament.pages.finance.profit-and-loss-report';

    public ?array $formData = [];
    public ?array $reportData = null;

    public function mount(): void
    {
        $this->form->fill([
            'startDate' => now()->startOfYear()->toDateString(),
            'endDate' => now()->toDateString(),
        ]);

        $this->generateReport();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(4)->schema([
                    DatePicker::make('startDate')
                        ->label('Start Date')
                        ->required(),
                    DatePicker::make('endDate')
                        ->label('End Date')
                        ->required(),
                    Select::make('scheduleId')
                        ->label('Account Schedule')
                        ->options(AccountSchedule::pluck('name', 'id'))
                        ->placeholder('Standard COA Layout')
                        ->searchable(),
                    Select::make('dimension1')
                        ->label('Department')
                        ->options(\App\Models\Department::pluck('name', 'department_code'))
                        ->placeholder('All Departments'),
                ]),
            ])
            ->statePath('formData');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Generate')
                ->action(fn() => $this->generateReport()),
            Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->extraAttributes([
                    'onclick' => 'window.print(); return false;',
                ]),
        ];
    }

    public function generateReport(): void
    {
        $service = app(IncomeStatementService::class);
        $start = Carbon::parse($this->formData['startDate']);
        $end = Carbon::parse($this->formData['endDate']);

        if (!empty($this->formData['scheduleId'])) {
            $rows = $service->generateFromSchedule(
                (int) $this->formData['scheduleId'],
                $start,
                $end,
                $this->formData['dimension1'] ?? null
            );

            $this->reportData = [
                'period' => "{$start->format('Y-m-d')}..{$end->format('Y-m-d')}",
                'lines' => $rows,
                'is_custom' => true,
            ];
        } else {
            $report = $service->generate(
                $start,
                $end,
                $this->formData['dimension1'] ?? null
            );

            $this->reportData = $report->toBcFormat();
            $this->reportData['is_custom'] = false;
        }
    }
}
