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
                    ->form([
                        Select::make('retained_earnings_account_id')
                            ->label('Retained Earnings Account')
                            ->options(
                                ChartOfAccount::query()
                                    ->whereIn('account_type', ['EQUITY', 'LIABILITY'])
                                    ->orderBy('account_number')
                                    ->get()
                                    ->mapWithKeys(fn (ChartOfAccount $account): array => [$account->id => "{$account->account_number} - {$account->name}"])
                                    ->toArray()
                            )
                            ->searchable()
                            ->required()
                            ->default(fn () => GeneralLedgerSetup::instance()->retained_earnings_account_id),
                        Select::make('default_expense_offset_account_id')
                            ->label('Default Expense Offset Account')
                            ->options(
                                ChartOfAccount::query()
                                    ->whereIn('account_category', [
                                        AccountCategory::ASSET->value,
                                        AccountCategory::LIQUID_ASSET->value,
                                        AccountCategory::LIABILITY->value,
                                        AccountCategory::PAYABLE->value,
                                    ])
                                    ->orderBy('account_number')
                                    ->get()
                                    ->mapWithKeys(fn (ChartOfAccount $account): array => [$account->id => "{$account->account_number} - {$account->name}"])
                                    ->toArray()
                            )
                            ->searchable()
                            ->nullable()
                            ->helperText('Used when posting an expense without a vendor or employee payable account.')
                            ->default(fn () => GeneralLedgerSetup::instance()->default_expense_offset_account_id),
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
                    ->form([
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
                    ->form([
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
                    ->form([
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
                    ->form([
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
                    ->form([
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
}
