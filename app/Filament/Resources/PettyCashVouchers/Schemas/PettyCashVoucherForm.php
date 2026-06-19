<?php

namespace App\Filament\Resources\PettyCashVouchers\Schemas;

use App\Enums\PettyCashVoucherStatus;
use App\Models\PettyCashFund;
use App\Services\NumberSeriesService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PettyCashVoucherForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Voucher Header')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('voucher_number')
                                    ->label('Voucher No.')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    // FIX: Use tryGetNextNo() to prevent crashes if series is not configured
                                    ->default(fn () => app(NumberSeriesService::class)->tryGetNextNo('PC-VOUCHER') ?? 'TEMP-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT))
                                    ->disabled()
                                    ->dehydrated(),

                                Select::make('petty_cash_fund_id')
                                    ->label('Petty Cash Fund')
                                    ->relationship('fund', 'name', fn ($query) => $query->where('is_active', true))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        $fund = PettyCashFund::find($state);
                                        $set('fund_balance', $fund?->current_balance);
                                        $set('currency', $fund?->currency ?? 'NGN');
                                    }),

                                TextInput::make('fund_balance')
                                    ->label('Fund Balance')
                                    ->numeric()
                                    ->prefix(fn (Get $get) => match ($get('currency') ?? 'NGN') { 'USD' => '$', 'EUR' => '€', 'GBP' => '£', default => '₦' })
                                    ->disabled()
                                    ->dehydrated()
                                    ->helperText('Current available balance in the selected fund.'),

                                DatePicker::make('date')
                                    ->label('Voucher Date')
                                    ->default(now())
                                    ->required()
                                    ->native(false),

                                TextInput::make('payee_name')
                                    ->label('Payee Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Person or vendor receiving payment'),

                                TextInput::make('payee_description')
                                    ->label('Payee Description')
                                    ->maxLength(255)
                                    ->placeholder('e.g., Office Cleaner, Taxi Driver'),

                                Select::make('status')
                                    ->options(PettyCashVoucherStatus::class)
                                    ->default(PettyCashVoucherStatus::PENDING)
                                    ->required()
                                    ->disabled(fn (string $context) => $context === 'create')
                                    ->dehydrated()
                                    ->native(false),

                                TextInput::make('currency')->hidden(),

                                Textarea::make('purpose')
                                    ->required()
                                    ->maxLength(1000)
                                    ->placeholder('Brief description of why this payment is needed')
                                    ->columnSpanFull(),

                                Textarea::make('notes')
                                    ->maxLength(1000)
                                    ->placeholder('Additional information, receipt references, etc.')
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make('Voucher Lines')
                    ->schema([
                        Repeater::make('lines')
                            ->relationship('lines')
                            ->schema([
                                TextInput::make('line_number')
                                    ->numeric()
                                    ->default(10000)
                                    ->step(10000)
                                    ->disabled()
                                    ->dehydrated()
                                    ->hidden(),

                                Select::make('expense_account_id')
                                    ->label('Expense Account')
                                    ->relationship(
                                        name: 'expenseAccount',
                                        titleAttribute: 'name', // chart_of_accounts does have a 'name' column
                                        modifyQueryUsing: fn ($query) => $query
                                            ->where('blocked', false)
                                            ->where('direct_posting', true) // Best practice: only allow accounts that accept direct posting
                                            ->whereIn('account_category', [
                                                \App\Enums\AccountCategory::DIRECT_EXPENSE,
                                                \App\Enums\AccountCategory::INDIRECT_EXPENSE,
                                                \App\Enums\AccountCategory::OPERATING_EXPENSE,
                                                \App\Enums\AccountCategory::OTHER_INCOME_EXPENSE,
                                                \App\Enums\AccountCategory::COGS,
                                            ])
                                    )
                                    ->searchable()
                                    ->getOptionLabelFromRecordUsing(
                                    // Display format: "6100 — Office Supplies"
                                        fn (\App\Models\ChartOfAccount $record) => "{$record->account_number} — {$record->name}"
                                    )
                                    ->getSearchResultsUsing(
                                        fn (string $search) => \App\Models\ChartOfAccount::where('blocked', false)
                                            ->where('direct_posting', true)
                                            ->whereIn('account_category', [
                                                \App\Enums\AccountCategory::DIRECT_EXPENSE,
                                                \App\Enums\AccountCategory::INDIRECT_EXPENSE,
                                                \App\Enums\AccountCategory::OPERATING_EXPENSE,
                                                \App\Enums\AccountCategory::OTHER_INCOME_EXPENSE,
                                                \App\Enums\AccountCategory::COGS,
                                            ])
                                            ->where(fn ($q) =>
                                            $q->where('account_number', 'like', "%{$search}%")
                                                ->orWhere('name', 'like', "%{$search}%")
                                                ->orWhere('search_name', 'like', "%{$search}%")
                                            )
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn ($item) => [$item->id => "{$item->account_number} — {$item->name}"])
                                    )
                                    ->preload()
                                    ->required(),

                                TextInput::make('description')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('What was purchased'),

                                TextInput::make('amount')
                                    ->required()
                                    ->numeric()
                                    ->prefix(fn (Get $get) => match ($get('../../../currency') ?? 'NGN') { 'USD' => '$', 'EUR' => '€', 'GBP' => '£', default => '₦' })
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        self::recalculateTotals($set, $get);
                                    }),

                                Select::make('dimension_department_id')
                                    ->label('Department')
                                    ->relationship('department', 'name')
                                    ->searchable()
                                    ->preload(),

                                Select::make('dimension_project_id')
                                    ->label('Project')
                                    ->relationship('project', 'name')
                                    ->searchable()
                                    ->preload(),

                                Textarea::make('notes')
                                    ->maxLength(500)
                                    ->placeholder('Receipt reference, etc.'),
                            ])
                            ->columns(2)
                            ->addActionLabel('Add Line')
                            ->reorderable()
                            ->collapsible()
                            ->live()
                            ->minItems(1) // Forces at least 1 line
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data, $record): array {
                                // Automatically calculate the next line_number (10,000 increment)
                                $maxLine = $record->lines()->max('line_number');
                                $data['line_number'] = $maxLine ? $maxLine + 10000 : 10000;

                                return $data;
                            })
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                self::recalculateTotals($set, $get);
                            }),
                    ]),

                Section::make('Totals')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('total_amount')
                                    ->label('Total Amount')
                                    ->numeric()
                                    ->prefix(fn (Get $get) => match ($get('currency') ?? 'NGN') { 'USD' => '$', 'EUR' => '€', 'GBP' => '£', default => '₦' })
                                    ->disabled()
                                    ->dehydrated(),
                            ]),
                    ]),

                Section::make('Approval & Posting')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('approved_by_id')
                                    ->label('Approved By')
                                    ->relationship('approvedBy', 'name')
                                    ->disabled()
                                    ->dehydrated(),

                                Select::make('posted_by_id')
                                    ->label('Posted By')
                                    ->relationship('postedBy', 'name')
                                    ->disabled()
                                    ->dehydrated(),

                                DatePicker::make('posted_at')
                                    ->label('Posted At')
                                    ->disabled()
                                    ->dehydrated(),
                            ]),
                        Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->maxLength(1000)
                            ->placeholder('Reason if voucher is rejected')
                            ->visible(fn (Get $get) => $get('status') === PettyCashVoucherStatus::REJECTED->value)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (string $context) => $context !== 'create'),
            ]);
    }

    private static function recalculateTotals(Set $set, Get $get): void
    {
        $lines = $get('../../lines') ?? [];
        $total = collect($lines)->filter()->sum('amount');
        $set('../../total_amount', round((float) $total, 2));
    }
}
