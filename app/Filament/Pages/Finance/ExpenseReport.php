<?php

namespace App\Filament\Pages\Finance;

use App\Models\ExpenseCategory;
use App\Models\ExpenseTransaction;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class ExpenseReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';

    protected static \UnitEnum|string|null $navigationGroup = 'Finance';

    protected static ?string $title = 'Expense Report';

    protected string $view = 'filament.pages.finance.expense-report';

    public ?array $formData = [];

    public function mount(): void
    {
        $this->form->fill([
            'period' => 'monthly',
            'anchorDate' => now()->toDateString(),
            'categoryCode' => null,
            'expenseType' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(4)->schema([
                    Select::make('period')
                        ->label('View')
                        ->options([
                            'daily' => 'Daily',
                            'weekly' => 'Weekly',
                            'monthly' => 'Monthly',
                        ])
                        ->required()
                        ->live(),
                    DatePicker::make('anchorDate')
                        ->label('Reference Date')
                        ->required()
                        ->live(),
                    Select::make('categoryCode')
                        ->label('Category')
                        ->placeholder('All categories')
                        ->options(fn (): array => ExpenseCategory::query()
                            ->where('is_active', true)
                            ->orderBy('category_code')
                            ->pluck('category_code', 'category_code')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->live(),
                    Select::make('expenseType')
                        ->label('Expense Type')
                        ->placeholder('All types')
                        ->options([
                            'direct' => 'Direct',
                            'indirect' => 'Indirect',
                        ])
                        ->live(),
                ]),
            ])
            ->statePath('formData');
    }

    public function generateReport(): void
    {
        // no-op; report is computed at render time
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn (): string => route('reports.expense.export', [
                    'format' => 'print',
                    'period' => $this->formData['period'] ?? 'monthly',
                    'anchorDate' => $this->formData['anchorDate'] ?? now()->toDateString(),
                    'categoryCode' => $this->formData['categoryCode'] ?? null,
                    'expenseType' => $this->formData['expenseType'] ?? null,
                ]), shouldOpenInNewTab: true),
            Action::make('csv')
                ->label('CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn (): string => route('reports.expense.export', [
                    'format' => 'csv',
                    'period' => $this->formData['period'] ?? 'monthly',
                    'anchorDate' => $this->formData['anchorDate'] ?? now()->toDateString(),
                    'categoryCode' => $this->formData['categoryCode'] ?? null,
                    'expenseType' => $this->formData['expenseType'] ?? null,
                ])),
        ];
    }

    public function reportData(): array
    {
        [$start, $end] = $this->resolvePeriod();

        $baseQuery = ExpenseTransaction::query()
            ->where('status', 'posted')
            ->whereBetween('posting_date', [$start->toDateString(), $end->toDateString()]);

        $selectedCategoryCode = filled($this->formData['categoryCode'] ?? null)
            ? (string) $this->formData['categoryCode']
            : null;
        $selectedExpenseType = filled($this->formData['expenseType'] ?? null)
            ? (string) $this->formData['expenseType']
            : null;

        if ($selectedCategoryCode !== null) {
            $baseQuery->where('category_code', $selectedCategoryCode);
        }
        if ($selectedExpenseType !== null) {
            $baseQuery->where('expense_type', $selectedExpenseType);
        }

        $transactions = (clone $baseQuery)->get();

        $summary = [
            'total_amount' => (float) $transactions->sum('amount'),
            'total_vat' => (float) $transactions->sum('vat_amount'),
            'count' => $transactions->count(),
            'average' => (float) $transactions->avg('amount'),
        ];

        $byCategory = $transactions
            ->groupBy(fn (ExpenseTransaction $transaction): string => (string) ($transaction->category_code ?: 'uncategorized'))
            ->map(fn ($group, $code): array => [
                'category_code' => (string) $code,
                'count' => $group->count(),
                'amount' => (float) $group->sum('amount'),
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();

        $byType = $transactions
            ->groupBy(fn (ExpenseTransaction $transaction): string => (string) ($transaction->expense_type ?: 'unspecified'))
            ->map(fn ($group, $type): array => [
                'expense_type' => (string) $type,
                'count' => $group->count(),
                'amount' => (float) $group->sum('amount'),
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();

        $trend = $transactions
            ->groupBy(fn (ExpenseTransaction $transaction): string => $this->bucketLabel($transaction->posting_date ? Carbon::parse($transaction->posting_date) : now()))
            ->map(fn ($group, $bucket): array => [
                'bucket' => (string) $bucket,
                'amount' => (float) $group->sum('amount'),
                'count' => $group->count(),
            ])
            ->sortBy('bucket')
            ->values()
            ->all();

        $latest = (clone $baseQuery)
            ->latest('posting_date')
            ->limit(20)
            ->get(['document_no', 'posting_date', 'category_code', 'expense_type', 'amount', 'status'])
            ->toArray();

        return [
            'period' => [
                'mode' => (string) ($this->formData['period'] ?? 'monthly'),
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'category_code' => $selectedCategoryCode,
                'expense_type' => $selectedExpenseType,
            ],
            'summary' => $summary,
            'by_category' => $byCategory,
            'by_type' => $byType,
            'trend' => $trend,
            'latest' => $latest,
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function resolvePeriod(): array
    {
        $anchor = Carbon::parse($this->formData['anchorDate'] ?? now()->toDateString());
        $mode = (string) ($this->formData['period'] ?? 'monthly');

        return match ($mode) {
            'daily' => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
            'weekly' => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()],
            default => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
        };
    }

    private function bucketLabel(Carbon $date): string
    {
        return match ((string) ($this->formData['period'] ?? 'monthly')) {
            'daily' => $date->format('Y-m-d'),
            'weekly' => $date->startOfWeek()->format('Y-m-d'),
            default => $date->format('Y-m'),
        };
    }
}
