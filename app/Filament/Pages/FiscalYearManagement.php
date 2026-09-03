<?php

namespace App\Filament\Pages;

use App\Enums\AccountCategory;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\FiscalReopenLog;
use App\Models\GeneralLedgerSetup;
use App\Services\FiscalWindowService;
use App\Services\FiscalYearCloseService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class FiscalYearManagement extends Page
{
    protected string $view = 'filament.pages.fiscal-year-management';

    protected static string|UnitEnum|null $navigationGroup = 'Accounting';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $title = 'Fiscal Year Management';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('super_admin') === true;
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('setup')
                    ->label('GL Fiscal Setup')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Select::make('retained_earnings_account_id')
                            ->label('Retained Earnings Account')
                            ->options(fn (): array => self::retainedEarningsAccountOptions())
                            ->searchable()
                            ->preload()
                            ->helperText(fn (): string => self::retainedEarningsAccountOptions() === []
                                ? 'Create an active, direct-posting Equity account in Chart of Accounts first.'
                                : 'The equity account that receives year-end profit or loss.')
                            ->required()
                            ->default(fn () => GeneralLedgerSetup::instance()->retained_earnings_account_id),
                        Select::make('default_expense_offset_account_id')
                            ->label('Default Expense Offset Account')
                            ->options(fn (): array => self::expenseOffsetAccountOptions())
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText(fn (): string => self::expenseOffsetAccountOptions() === []
                                ? 'Create an active, direct-posting asset, cash, liability, or payable account first.'
                                : 'Used when an expense has no vendor or employee payable account.')
                            ->default(fn () => GeneralLedgerSetup::instance()->default_expense_offset_account_id),
                        Select::make('opening_balance_equity_account_id')
                            ->label('Bank Opening Balance Offset Account')
                            ->options(fn (): array => ChartOfAccount::query()
                                ->where('structural_type', 'posting')
                                ->where('account_category', AccountCategory::EQUITY->value)
                                ->where('direct_posting', true)
                                ->where('blocked', false)
                                ->orderBy('account_number')
                                ->get()
                                ->filter(fn (ChartOfAccount $account): bool => ! $account->isSystemControlled())
                                ->mapWithKeys(fn (ChartOfAccount $account): array => [$account->id => $account->account_number.' - '.$account->name])
                                ->all())
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Used as the credit offset when a bank opening balance is posted.')
                            ->default(fn () => GeneralLedgerSetup::instance()->opening_balance_equity_account_id),
                        DatePicker::make('allow_posting_from')
                            ->required()
                            ->default(fn () => GeneralLedgerSetup::instance()->allow_posting_from),
                        DatePicker::make('allow_posting_to')
                            ->required()
                            ->default(fn () => GeneralLedgerSetup::instance()->allow_posting_to),
                    ])
                    ->action(function (array $data): void {
                        GeneralLedgerSetup::instance()->update($data);
                        Notification::make()->title('Fiscal setup saved')->success()->send();
                    }),
                Action::make('createPeriod')
                    ->label('Create Accounting Period')
                    ->icon('heroicon-o-plus')
                    ->schema([
                        TextInput::make('name')->required(),
                        DatePicker::make('start_date')->required(),
                        DatePicker::make('end_date')->required(),
                    ])
                    ->action(function (array $data): void {
                        $overlapExists = AccountingPeriod::query()
                            ->whereDate('start_date', '<=', $data['end_date'])
                            ->whereDate('end_date', '>=', $data['start_date'])
                            ->exists();

                        if ($overlapExists) {
                            Notification::make()->title('Period overlaps an existing accounting period')->danger()->send();

                            return;
                        }

                        AccountingPeriod::query()->create([
                            'name' => $data['name'],
                            'start_date' => $data['start_date'],
                            'end_date' => $data['end_date'],
                            'is_closed' => false,
                        ]);
                        Notification::make()->title('Accounting period created')->success()->send();
                    }),
                Action::make('closePeriod')
                    ->label('Close Accounting Period')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->schema([
                        Select::make('period_id')
                            ->label('Period')
                            ->options(fn () => AccountingPeriod::query()
                                ->where('is_closed', false)
                                ->orderBy('start_date')
                                ->get()
                                ->mapWithKeys(fn (AccountingPeriod $p): array => [$p->id => ($p->name ?: 'Period').' ('.$p->start_date?->toDateString().' to '.$p->end_date?->toDateString().')'])
                                ->toArray())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $period = AccountingPeriod::query()->find($data['period_id']);
                        if (! $period) {
                            Notification::make()->title('Accounting period not found')->danger()->send();

                            return;
                        }

                        $period->update([
                            'is_closed' => true,
                            'closed_at' => now(),
                            'closed_by' => auth()->id(),
                        ]);

                        Notification::make()->title('Accounting period closed')->success()->send();
                    }),
                Action::make('reopenPeriod')
                    ->label('Reopen Accounting Period')
                    ->icon('heroicon-o-lock-open')
                    ->color('warning')
                    ->schema([
                        Select::make('period_id')
                            ->label('Period')
                            ->options(fn () => AccountingPeriod::query()
                                ->where('is_closed', true)
                                ->orderByDesc('start_date')
                                ->get()
                                ->mapWithKeys(fn (AccountingPeriod $p): array => [$p->id => ($p->name ?: 'Period').' ('.$p->start_date?->toDateString().' to '.$p->end_date?->toDateString().')'])
                                ->toArray())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $period = AccountingPeriod::query()->find($data['period_id']);
                        if (! $period) {
                            Notification::make()->title('Accounting period not found')->danger()->send();

                            return;
                        }

                        $period->update([
                            'is_closed' => false,
                            'closed_at' => null,
                            'closed_by' => null,
                        ]);

                        Notification::make()->title('Accounting period reopened')->success()->send();
                    }),
            ])->label('Period Setup'),
            ActionGroup::make([
                Action::make('closeIncomeStatement')
                    ->label('Close Income Statement')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([
                        TextInput::make('fiscal_year')
                            ->numeric()
                            ->required()
                            ->default(now()->year),
                    ])
                    ->action(function (array $data, FiscalYearCloseService $service): void {
                        $result = $service->closeIncomeStatement((int) $data['fiscal_year'], (int) auth()->id());
                        Notification::make()
                            ->title("Close completed. Entries: {$result['entries_posted']}, Net: {$result['net_income']}")
                            ->success()
                            ->send();
                    }),
                Action::make('reopenWindow')
                    ->label('Reopen Posting Window')
                    ->icon('heroicon-o-lock-open')
                    ->color('warning')
                    ->schema([
                        DatePicker::make('allow_posting_from')->required(),
                        DatePicker::make('allow_posting_to')->required(),
                        TextInput::make('reason')->required()->maxLength(255),
                    ])
                    ->action(function (array $data, FiscalWindowService $service): void {
                        $service->reopenPostingWindow(
                            fromDate: (string) $data['allow_posting_from'],
                            toDate: (string) $data['allow_posting_to'],
                            reason: (string) $data['reason'],
                            userId: (int) auth()->id(),
                        );
                        Notification::make()->title('Posting window updated')->success()->send();
                    }),
            ])->label('Year-End Actions'),
        ];
    }

    public function getViewData(): array
    {
        return [
            'setup' => GeneralLedgerSetup::instance()->load('retainedEarningsAccount'),
            'periods' => AccountingPeriod::query()->orderByDesc('start_date')->limit(20)->get(),
            'reopenLogs' => FiscalReopenLog::query()->with('requester')->latest('id')->limit(20)->get(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function retainedEarningsAccountOptions(): array
    {
        return ChartOfAccount::query()
            ->retainedEarningsEligible()
            ->orderBy('account_number')
            ->get()
            ->mapWithKeys(fn (ChartOfAccount $account): array => [$account->id => "{$account->account_number} - {$account->name}"])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function expenseOffsetAccountOptions(): array
    {
        return ChartOfAccount::query()
            ->expenseOffsetEligible()
            ->orderBy('account_number')
            ->get()
            ->mapWithKeys(fn (ChartOfAccount $account): array => [$account->id => "{$account->account_number} - {$account->name}"])
            ->all();
    }
}
