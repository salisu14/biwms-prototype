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
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseStatisticsReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|null|\UnitEnum $navigationGroup = 'Finance';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?int $navigationSort = 11;

    // ✅ IMPROVED: Use proper Filament view path convention
    protected string $view = 'filament.pages.finance.purchase-statistics';

    // ✅ ADDED: For tracking report generation state
    public bool $hasGenerated = false;

    public ?array $data = [];
    public ?string $date_from = null;
    public ?string $date_to = null;
    public ?int $gen_bus_posting_group_id = null;

    /**
     * ✅ IMPROVED: Breadcrumb navigation
     */
    public function getBreadcrumb(): string
    {
        return 'Purchase Statistics';
    }

    /**
     * ✅ IMPROVED: Page title with dynamic data count
     */
    public function getTitle(): string
    {
        $count = count($this->data);
        return "Purchase Statistics" . ($count > 0 ? " ({$count} Groups)" : '');
    }

    public static function getNavigationLabel(): string
    {
        return 'Purchase Stats';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Reports & Analytics';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->can('view_reports');
    }

    /**
     * ✅ IMPROVED: Form schema with better validation
     */
    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Report Filters')
                ->description('Configure date range and optional filters')
                ->icon('heroicon-m-funnel')
                ->columns(['md' => 4, 'lg' => 4])
                ->schema([
                    DatePicker::make('date_from')
                        ->label('From Date')
                        ->default(now()->startOfMonth())
                        ->required()
                        ->maxDate(now())
                        ->reactive()
                        ->live(onBlur: true),

                    DatePicker::make('date_to')
                        ->label('To Date')
                        ->default(now())
                        ->required()
                        ->maxDate(now())
                        ->afterOrEqual('date_from')
                        ->reactive()
                        ->live(onBlur: true),

                    Select::make('gen_bus_posting_group_id')
                        ->label('Posting Group')
                        ->searchable()
                        ->options(
                            fn() => GeneralBusinessPostingGroup::active()
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

    /**
     * ✅ ENHANCED: Generate report with better error handling
     */
    public function generate(): void
    {
        try {
            $this->validate();

            $this->data = $this->getReportData();
            $this->hasGenerated = true;

            Notification::make()
                ->title('✅ Report Generated Successfully')
                ->body(count($this->data) . " posting group(s) found")
                ->success()
                ->duration(3000)
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('❌ Generation Failed')
                ->body($e->getMessage())
                ->danger()
                ->duration(5000)
                ->send();
        }
    }

    /**
     * ✅ NEW: Export to CSV functionality
     */
    public function export(): StreamedResponse
    {
        if (empty($this->data)) {
            Notification::make()
                ->title('⚠️ No Data to Export')
                ->body('Please generate the report first before exporting.')
                ->warning()
                ->send();

            return response()->noContent(204);
        }

        $filename = 'purchase-stats-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            // ✅ BOM for Excel compatibility with special characters (₦)
            fwrite($handle, "\xEF\xBB\xBF");

            // Header row
            fputcsv($handle, [
                '#',
                'Posting Group Code',
                'Posting Group Name',
                'Total Cost (₦)',
                'Transaction Count',
                'Average Cost (₦)',
                'Max Single Transaction (₦)',
                'Min Single Transaction (₦)',
                '% of Total',
                'Accounts Used',
            ]);

            // Data rows
            foreach ($this->data as $index => $row) {
                fputcsv($handle, [
                    $index + 1,
                    $row['group_code'] ?? '',
                    $row['group_name'] ?? 'Unassigned',
                    number_format($row['total_cost'] ?? 0, 2, '.', ''),
                    $row['transaction_count'] ?? 0,
                    number_format($row['avg_cost'] ?? 0, 2, '.', ''),
                    number_format($row['max_single_cost'] ?? 0, 2, '.', ''),
                    number_format($row['min_single_cost'] ?? 0, 2, '.', ''),
                    ($row['percentage_of_total'] ?? 0) . '%',
                    $row['accounts_used'] ?? 0,
                ]);
            }

            // Summary row
            $totalCost = $this->getTotalCost();
            $totalTxns = $this->getTotalTransactions();

            fputcsv($handle, [
                '',
                '',
                'TOTALS',
                number_format($totalCost, 2, '.', ''),
                $totalTxns,
                number_format($totalTxns > 0 ? $totalCost / $totalTxns : 0, 2, '.', ''),
                '',
                '',
                '100%',
                '',
            ]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0, must-revalidate',
            'Pragma' => 'public',
        ]);
    }

    /**
     * ✅ NEW: Export to JSON (alternative format)
     */
    public function exportJson(): \Illuminate\Http\RedirectResponse
    {
        if (empty($this->data)) {
            Notification::make()->title('No Data')->warning()->send();
            return back();
        }

        $exportData = [
            'generated_at' => now()->toIso8601String(),
            'filters' => [
                'date_from' => $this->date_from,
                'date_to' => $this->date_to,
                'group_id' => $this->gen_bus_posting_group_id,
            ],
            'summary' => [
                'total_cost' => $this->getTotalCost(),
                'total_transactions' => $this->getTotalTransactions(),
                'avg_cost' => $this->getAvgCost(),
                'groups_count' => count($this->data),
            ],
            'data' => $this->data,
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'purchase_stats_');
        file_put_contents($tempFile, json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return response()->download(
            $tempFile,
            'purchase-stats-' . now()->format('Y-m-d-His') . '.json'
        )->deleteFileAfterSend(true);
    }

    /**
     * ✅ OPTIMIZED: Query with performance improvements
     */
    private function getReportData(): array
    {
        $from = $this->date_from
            ? \Carbon\Carbon::parse($this->date_from)->startOfDay()
            : now()->startOfMonth();

        $to = $this->date_to
            ? \Carbon\Carbon::parse($this->date_to)->endOfDay()
            : now();

        // ✅ IMPROVED: Use query builder with indexes
        $query = GlEntry::query()
            ->join('chart_of_accounts as coa', 'gl_entries.chart_of_account_id', '=', 'coa.id')
            ->leftJoin('general_business_posting_groups as gbpg', 'coa.gen_bus_posting_group_id', '=', 'gbpg.id')
            ->whereBetween('gl_entries.posting_date', [$from, $to])
            ->where('gl_entries.debit_amount', '>', 0)
            ->where('coa.structural_type', 'posting') // Adjust based on your enum
            ->select(
                'gbpg.id as gen_bus_posting_group_id',
                'gbpg.code as group_code',
                'gbpg.description as group_name',
                DB::raw('COALESCE(SUM(gl_entries.debit_amount), 0) as total_cost'),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('COALESCE(AVG(gl_entries.debit_amount), 0) as avg_cost'),
                DB::raw('COALESCE(MAX(gl_entries.debit_amount), 0) as max_single_cost'),
                DB::raw('COALESCE(MIN(gl_entries.debit_amount), 0) as min_single_cost'),
                DB::raw('COUNT(DISTINCT gl_entries.chart_of_account_id) as accounts_used'),
            )
            ->groupBy('gbpg.id', 'gbpg.code', 'gbpg.description')
            ->orderByDesc('total_cost'); // ✅ Sort by highest cost first

        // ✅ Filter by specific group if selected
        if (!empty($this->gen_bus_posting_group_id)) {
            $query->where('gbpg.id', $this->gen_bus_posting_group_id);
        }

        $results = $query->get();

        // ✅ Process results with percentage calculation
        $totalCost = $results->sum('total_cost');

        return $results->map(function ($row) use ($totalCost) {
            return [
                'group_id' => $row->gen_bus_posting_group_id,
                'group_code' => $row->group_code ?? 'N/A',
                'group_name' => $row->group_name ?? 'Unassigned',
                'total_cost' => round((float) ($row->total_cost ?? 0), 2),
                'transaction_count' => (int) ($row->transaction_count ?? 0),
                'avg_cost' => round((float) ($row->avg_cost ?? 0), 2),
                'max_single_cost' => round((float) ($row->max_single_cost ?? 0), 2),
                'min_single_cost' => round((float) ($row->min_single_cost ?? 0), 2),
                'accounts_used' => (int) ($row->accounts_used ?? 0),
                'percentage_of_total' => $totalCost > 0
                    ? round((($row->total_cost ?? 0) / $totalCost) * 100, 2)
                    : 0,
            ];
        })->values()->toArray(); // ✅ values() to re-index from 0
    }

    // ✅ Helper methods (unchanged but optimized)
    public function getTotalCost(): float
    {
        return round(array_sum(array_column($this->data, 'total_cost')), 2);
    }

    public function getTotalTransactions(): int
    {
        return array_sum(array_column($this->data, 'transaction_count'));
    }

    public function getAvgCost(): float
    {
        $total = $this->getTotalCost();
        $count = $this->getTotalTransactions();
        return $count > 0 ? round($total / $count, 2) : 0;
    }

    /**
     * ✅ IMPROVED: View data preparation
     */
    public function getViewData(): array
    {
        return [
            'report_data' => $this->data,
            'summary' => [
                'total_cost' => $this->getTotalCost(),
                'total_transactions' => $this->getTotalTransactions(),
                'avg_cost' => $this->getAvgCost(),
                'groups_count' => count($this->data),
            ],
            'filters' => [
                'date_from' => $this->date_from,
                'date_to' => $this->date_to,
                'gen_bus_posting_group_id' => $this->gen_bus_posting_group_id,
            ],
            'has_generated' => $this->hasGenerated,
        ];
    }

    /**
     * ✅ ENHANCED: Header actions with full functionality
     */
    protected function getHeaderActions(): array
    {
        return [
            // Primary: Generate Report
            Action::make('generate')
                ->label('Generate Report')
                ->color('primary')
                ->icon('heroicon-o-chart-pie')
                ->submit('generate')
                ->requiresConfirmation(false),

            // Secondary: Export CSV (NOW WORKING!)
            Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-on-square-stack')
                ->color('success')
                ->action(fn () => $this->export()) // ✅ NOW CALLS THE METHOD!
                ->disabled(fn () => empty($this->data)) // Disable if no data
                ->tooltip(empty($this->data) ? 'Generate report first' : 'Download CSV'),

            // Tertiary: Print
            Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->action(fn () => $this->js('window.print()')),

            // Optional: Export JSON
            Action::make('exportJson')
                ->label('Export JSON')
                ->icon('heroicon-o-code-bracket')
                ->color('info')
                ->action(fn () => $this->exportJson())
                ->disabled(fn () => empty($this->data))
                ->hidden(), // Hide by default, show only when needed
        ];
    }

    /**
     * ✅ IMPROVED: Widgets with conditional rendering
     */
    public function getWidgets(): array
    {
        // Only show widgets after report is generated
        if (!$this->hasGenerated || empty($this->data)) {
            return [];
        }

        return [
            \App\Filament\Widgets\Reports\PurchaseSummaryWidget::make([
                'total_cost' => $this->getTotalCost(),
                'total_transactions' => $this->getTotalTransactions(),
                'avg_cost' => $this->getAvgCost(),
                'groups_count' => count($this->data),
                'date_range' => "{$this->date_from} to {$this->date_to}",
            ]),

            \App\Filament\Widgets\Reports\PurchaseChartWidget::make([
                'data' => $this->data,
            ]),
        ];
    }
}
