<?php

namespace App\Filament\Pages\Finance;

use App\Models\GeneralBusinessPostingGroup;
use App\Services\Finance\StatisticsReportService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SalesStatisticsReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static \UnitEnum|string|null $navigationGroup = 'Finance';

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.finance.sales-statistics';

    public ?string $date_from = null;

    public ?string $date_to = null;

    public ?int $gen_bus_posting_group_id = null;

    public function mount(StatisticsReportService $statisticsReportService): void
    {
        $filters = $statisticsReportService->normalizeFilters();

        $this->date_from = $filters['date_from'];
        $this->date_to = $filters['date_to'];
        $this->gen_bus_posting_group_id = $filters['gen_bus_posting_group_id'];
    }

    public function getBreadcrumb(): string
    {
        return 'Sales Statistics';
    }

    public function getTitle(): string
    {
        return 'Sales Statistics';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Reports & Analytics';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('view_reports') === true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Report Filters')
                ->columns(['md' => 4])
                ->schema([
                    DatePicker::make('date_from')
                        ->label('From Date')
                        ->required()
                        ->live(onBlur: true),

                    DatePicker::make('date_to')
                        ->label('To Date')
                        ->required()
                        ->afterOrEqual('date_from')
                        ->live(onBlur: true),

                    Select::make('gen_bus_posting_group_id')
                        ->label('Posting Group')
                        ->options(fn (): array => GeneralBusinessPostingGroup::active()
                            ->orderBy('code')
                            ->pluck('description', 'id')
                            ->all())
                        ->placeholder('All Groups')
                        ->searchable()
                        ->preload()
                        ->live(),
                ]),
        ]);
    }

    public function generate(): void
    {
        $this->validate();

        Notification::make()
            ->title('Report refreshed')
            ->success()
            ->send();
    }

    public function getViewData(): array
    {
        return [
            'report' => app(StatisticsReportService::class)->sales($this->filters()),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn (): string => route('reports.sales-statistics.export', [
                    ...$this->filters(),
                    'format' => 'print',
                ]), shouldOpenInNewTab: true),

            Action::make('csv')
                ->label('CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(fn (): string => route('reports.sales-statistics.export', [
                    ...$this->filters(),
                    'format' => 'csv',
                ])),

            Action::make('generate')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->submit('generate'),
        ];
    }

    /**
     * @return array{date_from: string|null, date_to: string|null, gen_bus_posting_group_id: int|null}
     */
    private function filters(): array
    {
        return [
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
            'gen_bus_posting_group_id' => $this->gen_bus_posting_group_id,
        ];
    }
}
