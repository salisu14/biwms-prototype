<?php

namespace App\Filament\Resources\RecurringJournalBatches\RelationManagers;

use App\Enums\RecurringMethod;
use App\Models\ChartOfAccount;
use App\Models\DimensionValue;
use App\Models\ReasonCode;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Recurring Lines';

    protected static ?string $recordTitleAttribute = 'line_no';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('Line Details')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('Schedule')
                            ->icon('heroicon-o-arrow-path')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('line_no')
                                        ->label('Line No.')
                                        ->numeric()
                                        ->required()
                                        ->step(10000),

                                    Select::make('recurring_method')
                                        ->label('Recurring Method')
                                        ->options(RecurringMethod::class)
                                        ->required()
                                        ->native(false)
                                        ->live()
                                        ->helperText('Fixed — same amount every period. Variable — enter amount each time. Balance — posts current account balance.'),

                                    Select::make('line_status')
                                        ->label('Status')
                                        ->options([
                                            'active' => 'Active',
                                            'on_hold' => 'On Hold',
                                            'expired' => 'Expired',
                                        ])
                                        ->default('active')
                                        ->native(false),
                                ]),

                                Grid::make(3)->schema([
                                    DatePicker::make('starting_date')
                                        ->label('Starting Date')
                                        ->required()
                                        ->native(false),

                                    DatePicker::make('ending_date')
                                        ->label('Ending Date')
                                        ->native(false)
                                        ->afterOrEqual('starting_date'),

                                    DatePicker::make('expiration_date')
                                        ->label('Expiration Date')
                                        ->native(false)
                                        ->helperText('Line stops recurring after this date.'),
                                ]),

                                Grid::make(3)->schema([
                                    DatePicker::make('last_posting_date')
                                        ->label('Last Posted')
                                        ->native(false)
                                        ->readOnly(),

                                    DatePicker::make('next_posting_date')
                                        ->label('Next Due')
                                        ->native(false)
                                        ->readOnly(),

                                    TextInput::make('posting_count')
                                        ->label('Times Posted')
                                        ->numeric()
                                        ->readOnly()
                                        ->default(0),
                                ]),
                            ]),

                        Tabs\Tab::make('Account')
                            ->icon('heroicon-o-building-library')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('account_type')
                                        ->label('Account Type')
                                        ->options([
                                            'gl' => 'G/L Account',
                                            'customer' => 'Customer',
                                            'vendor' => 'Vendor',
                                            'bank_account' => 'Bank Account',
                                        ])
                                        ->default('gl')
                                        ->required()
                                        ->native(false),

                                    Select::make('account_id')
                                        ->label('Account No.')
                                        ->relationship('account', 'name')
                                        ->required()
                                        ->searchable()
                                        ->preload()
                                        ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->no} - {$record->name}"),
                                ]),

                                Grid::make(2)->schema([
                                    Select::make('balancing_account_id')
                                        ->label('Bal. Account No.')
                                        ->relationship('balancingAccount', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->no} - {$record->name}"),

                                    Textarea::make('description')
                                        ->rows(2)
                                        ->required(),
                                ]),
                            ]),

                        Tabs\Tab::make('Amount')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('amount')
                                        ->label('Fixed Amount')
                                        ->numeric()
                                        ->helperText('Used for Fixed and Reversing Fixed methods.')
                                        ->visible(fn ($get) => in_array($get('recurring_method'), [
                                            'fixed', 'reversing_fixed',
                                        ])),

                                    TextInput::make('percentage_for_balance')
                                        ->label('% of Balance')
                                        ->numeric()
                                        ->suffix('%')
                                        ->helperText('Used for Balance/Reversing Balance methods.')
                                        ->visible(fn ($get) => in_array($get('recurring_method'), [
                                            'balance', 'reversing_balance',
                                        ])),

                                    TextInput::make('account_to_calculate_balance')
                                        ->label('Balance Account No.')
                                        ->maxLength(50)
                                        ->helperText('G/L account whose balance is used in the calculation.')
                                        ->visible(fn ($get) => in_array($get('recurring_method'), [
                                            'balance', 'reversing_balance',
                                        ])),

                                    Textarea::make('calculation_formula')
                                        ->label('Calculation Formula')
                                        ->rows(2)
                                        ->helperText('e.g. GL(1000).Balance * 0.05 — for Variable methods.')
                                        ->visible(fn ($get) => in_array($get('recurring_method'), [
                                            'variable', 'reversing_variable',
                                        ]))
                                        ->columnSpanFull(),
                                ]),

                                Grid::make(2)->schema([
                                    Toggle::make('use_allocation')
                                        ->label('Use Allocation')
                                        ->live()
                                        ->helperText('Distribute amount across dimensions via an allocation record.'),

                                    Select::make('allocation_id')
                                        ->label('Allocation')
                                        ->relationship('allocation', 'id')
                                        ->searchable()
                                        ->visible(fn ($get) => $get('use_allocation')),
                                ]),
                            ]),

                        Tabs\Tab::make('Dimensions')
                            ->icon('heroicon-o-squares-2x2')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('shortcut_dimension_1_code')
                                        ->label('Department (Dim 1)')
                                        ->options(fn () => DimensionValue::whereHas(
                                            'dimension',
                                            fn ($q) => $q->where('code', 'DEPARTMENT')
                                        )->pluck('name', 'code'))
                                        ->searchable()
                                        ->preload(),

                                    Select::make('shortcut_dimension_2_code')
                                        ->label('Project (Dim 2)')
                                        ->options(fn () => DimensionValue::whereHas(
                                            'dimension',
                                            fn ($q) => $q->where('code', 'PROJECT')
                                        )->pluck('name', 'code'))
                                        ->searchable()
                                        ->preload(),
                                ]),
                            ]),

                        Tabs\Tab::make('Audit')
                            ->icon('heroicon-o-magnifying-glass')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('source_code')
                                        ->label('Source Code')
                                        ->maxLength(20),

                                    Select::make('reason_code')
                                        ->label('Reason Code')
                                        ->options(fn () => ReasonCode::query()
                                            ->where('blocked', false)
                                            ->orderBy('code')
                                            ->pluck('description', 'code'))
                                        ->searchable()
                                        ->preload(),
                                ]),
                            ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('line_no')
            ->columns([
                TextColumn::make('line_no')
                    ->label('Line')
                    ->sortable()
                    ->width('60px'),

                TextColumn::make('recurring_method')
                    ->label('Method')
                    ->badge()
                    ->color(fn ($state) => match ((string) ($state?->value ?? $state)) {
                        'fixed' => 'info',
                        'variable' => 'warning',
                        'balance' => 'gray',
                        default => 'danger',
                    }),

                TextColumn::make('account.name')
                    ->label('Account')
                    ->limit(25)
                    ->searchable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(30)
                    ->placeholder('—'),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->numeric(decimalPlaces: 2)
                    ->alignment('right')
                    ->placeholder('Variable'),

                TextColumn::make('starting_date')
                    ->label('Start')
                    ->date(),

                TextColumn::make('expiration_date')
                    ->label('Expires')
                    ->date()
                    ->placeholder('Open')
                    ->toggleable(),

                TextColumn::make('last_posting_date')
                    ->label('Last Posted')
                    ->date()
                    ->placeholder('Never')
                    ->toggleable(),

                TextColumn::make('next_posting_date')
                    ->label('Next Due')
                    ->date()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('posting_count')
                    ->label('# Posted')
                    ->alignment('right')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('line_status')
                    ->badge()
                    ->color(fn ($state) => match ((string) $state) {
                        'active' => 'success',
                        'on_hold' => 'warning',
                        'expired' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('line_status')
                    ->options(['active' => 'Active', 'on_hold' => 'On Hold', 'expired' => 'Expired'])
                    ->native(false),

                SelectFilter::make('recurring_method')
                    ->options(RecurringMethod::class)
                    ->native(false),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Line')
                    ->icon('heroicon-o-plus-circle')
                    ->mutateFormDataUsing(function (array $data) {
                        if (empty($data['line_no'])) {
                            $max = $this->getOwnerRecord()->lines()->max('line_no') ?? 0;
                            $data['line_no'] = $max + 10000;
                        }

                        $data['line_status'] = 'active';

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(fn ($record) => $record->line_status === 'expired'),
                DeleteAction::make()
                    ->hidden(fn ($record) => $record->posting_count > 0),
            ])
            ->defaultSort('line_no');
    }
}
