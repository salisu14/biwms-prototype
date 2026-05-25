<?php

namespace App\Filament\Pages\Finance;

use App\Models\AccountSchedule;
use App\Models\DimensionValue;
use App\Services\IncomeStatementService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
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
                Tabs::make('Filters')
                    ->tabs([
                        Tab::make('General')
                            ->icon('heroicon-m-adjustments-horizontal')
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
                                        ->options(DimensionValue::whereHas('dimension', fn ($q) => $q->where('code', 'DEPARTMENT'))->pluck('name', 'code'))
                                        ->placeholder('All Departments')
                                        ->searchable(),
                                ]),
                            ]),
                        Tab::make('Comparison & Dimensions')
                            ->icon('heroicon-m-presentation-chart-line')
                            ->schema([
                                Grid::make(4)->schema([
                                    DatePicker::make('compareStartDate')
                                        ->label('Comparison Start Date'),
                                    DatePicker::make('compareEndDate')
                                        ->label('Comparison End Date'),
                                    Select::make('dimension2')
                                        ->label('Project')
                                        ->options(DimensionValue::whereHas('dimension', fn ($q) => $q->where('code', 'PROJECT'))->pluck('name', 'code'))
                                        ->placeholder('All Projects')
                                        ->searchable(),
                                    Toggle::make('showBudget')
                                        ->label('Show Budget')
                                        ->inline(false),
                                ]),
                            ]),
                    ]),
            ])
            ->statePath('formData');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Generate')
                ->action(fn () => $this->generateReport()),
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

        $compareStart = ! empty($this->formData['compareStartDate']) ? Carbon::parse($this->formData['compareStartDate']) : null;
        $compareEnd = ! empty($this->formData['compareEndDate']) ? Carbon::parse($this->formData['compareEndDate']) : null;

        if (! empty($this->formData['scheduleId'])) {
            $rows = $service->generateFromSchedule(
                (int) $this->formData['scheduleId'],
                $start,
                $end,
                $this->formData['dimension1'] ?? null,
                $this->formData['dimension2'] ?? null
            );

            $this->reportData = [
                'report_name' => 'Income Statement (Account Schedule)',
                'printed_at' => now()->format('Y-m-d H:i'),
                'period' => "{$start->format('Y-m-d')}..{$end->format('Y-m-d')}",
                'lines' => $rows->map(fn (array $row): array => [
                    'description' => $row['description'] ?? '',
                    'indentation' => $row['indentation'] ?? 0,
                    'bold' => $row['bold'] ?? false,
                    'amount' => number_format((float) ($row['amount'] ?? 0), 2),
                    'compare_amount' => null,
                    'variance_percent' => null,
                ]),
                'totals' => [
                    'revenue' => number_format(0, 2),
                    'cogs' => number_format(0, 2),
                    'gross_profit' => number_format(0, 2),
                    'operating_expenses' => number_format(0, 2),
                    'operating_income' => number_format(0, 2),
                    'net_income' => number_format((float) $rows->sum('amount'), 2),
                    'compare_revenue' => number_format(0, 2),
                    'compare_gross_profit' => number_format(0, 2),
                    'compare_operating_expenses' => number_format(0, 2),
                    'compare_net_income' => number_format(0, 2),
                ],
                'is_custom' => true,
            ];
        } else {
            $report = $service->generate(
                fromDate: $start,
                toDate: $end,
                globalDimension1: $this->formData['dimension1'] ?? null,
                globalDimension2: $this->formData['dimension2'] ?? null,
                compareFrom: $compareStart,
                compareTo: $compareEnd,
                showBudget: $this->formData['showBudget'] ?? false
            );

            $this->reportData = $report->toBcFormat();
            $this->reportData['is_custom'] = false;

            // Add comparison period to report data for the UI
            if ($compareStart && $compareEnd) {
                $this->reportData['compare_period'] = "{$compareStart->format('Y-m-d')}..{$compareEnd->format('Y-m-d')}";
            }
        }
    }
}
