<?php

namespace App\Filament\Pages\Finance;

use App\Models\GeneralBusinessPostingGroup;
use App\Models\GlEntry;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class SalesStatisticsReport  extends Page implements HasForms
{
    use InteractsWithForms;

    protected static \UnitEnum|string|null $navigationGroup = 'Finance';
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?int $navigationSort = 10;
    protected string $view = 'filament.pages.finance.sales-statistics';

    public ?array $data = [];
    public $date_from;
    public $date_to;
    public $gen_bus_posting_group_id;

    public function getBreadcrumb(): string
    {
        return 'Reports / Sales Statistics';
    }

    public function getTitle(): string
    {
        return 'Sales Statistics - Revenue by Gen. Bus. Posting Group';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Reports & Analytics';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->can('view_reports');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Report Filters')
                ->columns(4)
                ->schema([
                    DatePicker::make('date_from')
                        ->label('From Date')
                        ->default(now()->startOfMonth())
                        ->required()
                        ->reactive(),

                    DatePicker::make('date_to')
                        ->label('To Date')
                        ->default(now())
                        ->required()
                        ->reactive(),

                    Select::make('gen_bus_posting_group_id')
                        ->label('Gen. Bus. Posting Group')
                        ->options(
                            GeneralBusinessPostingGroup::active()
                                ->orderBy('code')
                                ->pluck('description', 'id')
                                ->toArray()
                        )
                        ->placeholder('All Groups')
                        ->nullable()
                        ->reactive(),
                ]),
        ]);
    }

    public function generate(): void
    {
        $this->validate();
        $this->data = $this->getReportData();

        Notification::make()
            ->title('Report generated successfully')
            ->success()
            ->send();
    }

    /**
     * Get sales/revenue data.
     *
     * In BC terms: Revenue = Credit entries to Income Statement accounts
     * Grouped by the Gen. Bus. Posting Group of the G/L Account
     */
    private function getReportData(): array
    {
        $from = $this->date_from ?? now()->startOfMonth()->toDateString();
        $to = $this->date_to ?? now()->toDateString();

        // Build query: GlEntry → ChartOfAccount → GenBusPostingGroup
        $query = GlEntry::query()
            ->join('chart_of_accounts as coa', 'gl_entries.chart_of_account_id', '=', 'coa.id')
            ->leftJoin('general_business_posting_groups as gbpg', 'coa.gen_bus_posting_group_id', '=', 'gbpg.id')
            ->whereBetween('gl_entries.posting_date', [$from, $to])
            // Revenue = Credit entries (or we check account type)
            ->where('gl_entries.credit_amount', '>', 0)
            // Only posting accounts (not total/header accounts)
            ->where('coa.structural_type', 'posting') // Adjust based on your enum value
            ->select(
                'gbpg.id as gen_bus_posting_group_id',
                'gbpg.code as group_code',
                'gbpg.description as group_name',
                DB::raw('SUM(gl_entries.credit_amount) as total_revenue'),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('AVG(gl_entries.credit_amount) as avg_transaction'),
                DB::raw('COUNT(DISTINCT gl_entries.chart_of_account_id) as accounts_used'),
            )
            ->groupBy('gbpg.id', 'gbpg.code', 'gbpg.description');

        if ($this->gen_bus_posting_group_id) {
            $query->where('gbpg.id', $this->gen_bus_posting_group_id);
        }

        $results = $query->get();

        return $results->map(function ($row) {
            return [
                'group_id' => $row->gen_bus_posting_group_id,
                'group_code' => $row->group_code,
                'group_name' => $row->group_name ?? 'Unassigned',
                'total_revenue' => (float) ($row->total_revenue ?? 0),
                'transaction_count' => (int) ($row->transaction_count ?? 0),
                'avg_transaction' => (float) ($row->avg_transaction ?? 0),
                'accounts_used' => (int) ($row->accounts_used ?? 0),
                'percentage_of_total' => 0,
            ];
        })->toArray();
    }

    public function getTotalRevenue(): float
    {
        return array_sum(array_column($this->data, 'total_revenue'));
    }

    public function getTotalTransactions(): int
    {
        return array_sum(array_column($this->data, 'transaction_count'));
    }

    public function getAvgTransactionSize(): float
    {
        $total = $this->getTotalRevenue();
        $count = $this->getTotalTransactions();
        return $count > 0 ? round($total / $count, 2) : 0;
    }

    public function getViewData(): array
    {
        $totalRevenue = $this->getTotalRevenue();

        foreach ($this->data as &$row) {
            $row['percentage_of_total'] = $totalRevenue > 0
                ? round(($row['total_revenue'] / $totalRevenue) * 100, 2)
                : 0;
        }

        return [
            'report_data' => $this->data,
            'summary' => [
                'total_revenue' => $this->getTotalRevenue(),
                'total_transactions' => $this->getTotalTransactions(),
                'avg_transaction' => $this->getAvgTransactionSize(),
                'groups_count' => count($this->data),
            ],
            'filters' => [
                'date_from' => $this->date_from,
                'date_to' => $this->date_to,
                'gen_bus_posting_group_id' => $this->gen_bus_posting_group_id,
            ],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Generate Report')
                ->color('primary')
                ->icon('heroicon-o-chart-bar')
                ->submit('generate'),

            Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-on-square')
                ->color('success')
                ->action(function () {
                    // Export logic here
                    Notification::make()
                        ->title('Export started')
                        ->success()
                        ->send();
                }),

            Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->action(fn () => $this->js('window.print()')),
        ];
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\Reports\SalesSummaryWidget::make([
                'total_revenue' => $this->getTotalRevenue(),
                'total_transactions' => $this->getTotalTransactions(),
                'avg_transaction' => $this->getAvgTransactionSize(),
                'groups_count' => count($this->data),
                'date_range' => "{$this->date_from} to {$this->date_to}",
            ]),

            \App\Filament\Widgets\Reports\SalesChartWidget::make([
                'data' => $this->data,
            ]),
        ];
    }
}
