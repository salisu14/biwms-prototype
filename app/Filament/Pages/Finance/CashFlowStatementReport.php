<?php

declare(strict_types=1);

namespace App\Filament\Pages\Finance;

use App\Models\AccountSchedule;
use App\Services\Finance\CashFlowStatementService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class CashFlowStatementReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static \UnitEnum|string|null $navigationGroup = 'Finance';

    protected static ?string $title = 'Cash Flow Statement';

    protected string $view = 'filament.pages.finance.cash-flow-statement-report';

    public ?array $formData = [];

    public function mount(): void
    {
        $this->form->fill([
            'startDate' => now()->startOfMonth()->toDateString(),
            'endDate' => now()->toDateString(),
            'compareStartDate' => null,
            'compareEndDate' => null,
            'method' => 'indirect',
            'cashFlowScheduleId' => AccountSchedule::query()->where('name', 'Default Cash Flow Statement')->value('id'),
            'profitAndLossScheduleId' => AccountSchedule::query()->where('name', 'Default Profit & Loss')->value('id'),
            'balanceSheetScheduleId' => AccountSchedule::query()->where('name', 'Default Balance Sheet')->value('id'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('startDate')
                    ->label('Start Date')
                    ->required(),
                DatePicker::make('endDate')
                    ->label('End Date')
                    ->required(),
                DatePicker::make('compareStartDate')
                    ->label('Comparison Start Date'),
                DatePicker::make('compareEndDate')
                    ->label('Comparison End Date'),
                Select::make('method')
                    ->label('Method')
                    ->options([
                        'indirect' => 'Indirect Method',
                        'direct' => 'Direct Method',
                    ])
                    ->required()
                    ->default('indirect')
                    ->native(false),
                Select::make('cashFlowScheduleId')
                    ->label('Cash Flow Schedule')
                    ->options(AccountSchedule::query()->orderBy('name')->pluck('name', 'id'))
                    ->placeholder('Auto-detect')
                    ->searchable()
                    ->preload()
                    ->native(false),
                Select::make('profitAndLossScheduleId')
                    ->label('P&L Schedule')
                    ->options(AccountSchedule::query()->orderBy('name')->pluck('name', 'id'))
                    ->placeholder('Auto-detect')
                    ->searchable()
                    ->preload()
                    ->native(false),
                Select::make('balanceSheetScheduleId')
                    ->label('Balance Sheet Schedule')
                    ->options(AccountSchedule::query()->orderBy('name')->pluck('name', 'id'))
                    ->placeholder('Auto-detect')
                    ->searchable()
                    ->preload()
                    ->native(false),
            ])
            ->statePath('formData');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function generateReport(): void
    {
        // Re-render only. Report data is derived from current form state.
    }

    public function reportData(): array
    {
        $startDate = Carbon::parse($this->formData['startDate'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $endDate = Carbon::parse($this->formData['endDate'] ?? now()->toDateString())->endOfDay();
        $compareStartDate = filled($this->formData['compareStartDate'] ?? null)
            ? Carbon::parse($this->formData['compareStartDate'])->startOfDay()
            : null;
        $compareEndDate = filled($this->formData['compareEndDate'] ?? null)
            ? Carbon::parse($this->formData['compareEndDate'])->endOfDay()
            : null;
        $method = (string) ($this->formData['method'] ?? 'indirect');
        $cashFlowScheduleId = filled($this->formData['cashFlowScheduleId'] ?? null)
            ? (int) $this->formData['cashFlowScheduleId']
            : null;
        $profitAndLossScheduleId = filled($this->formData['profitAndLossScheduleId'] ?? null)
            ? (int) $this->formData['profitAndLossScheduleId']
            : null;
        $balanceSheetScheduleId = filled($this->formData['balanceSheetScheduleId'] ?? null)
            ? (int) $this->formData['balanceSheetScheduleId']
            : null;

        return app(CashFlowStatementService::class)->generateComparison(
            $startDate,
            $endDate,
            $compareStartDate,
            $compareEndDate,
            $method,
            $cashFlowScheduleId,
            $profitAndLossScheduleId,
            $balanceSheetScheduleId
        );
    }
}
