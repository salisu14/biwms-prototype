<?php

namespace App\Filament\Resources\GeneralJournalBatches\RelationManagers;

use App\Enums\JournalLineStatus;
use App\Models\ChartOfAccount;
use App\Models\DimensionValue;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

    protected static ?string $title = 'Journal Lines';

    protected static ?string $recordTitleAttribute = 'line_no';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('Line Details')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('Entry')
                            ->icon('heroicon-o-pencil-square')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('line_no')
                                        ->label('Line No.')
                                        ->numeric()
                                        ->required()
                                        ->step(10000),

                                    DatePicker::make('posting_date')
                                        ->label('Posting Date')
                                        ->required()
                                        ->native(false)
                                        ->default(now()),

                                    Select::make('document_type')
                                        ->label('Document Type')
                                        ->options([
                                            '' => '(blank)',
                                            'payment' => 'Payment',
                                            'invoice' => 'Invoice',
                                            'credit_memo' => 'Credit Memo',
                                            'finance_charge_memo' => 'Finance Charge Memo',
                                            'reminder' => 'Reminder',
                                            'refund' => 'Refund',
                                        ])
                                        ->native(false)
                                        ->placeholder('(blank)'),
                                ]),

                                Grid::make(2)->schema([
                                    TextInput::make('document_no')
                                        ->label('Document No.'),

                                    TextInput::make('external_document_no')
                                        ->label('External Doc. No.'),
                                ]),

                                Textarea::make('description')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('Account')
                            ->icon('heroicon-o-building-library')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('account_type')
                                        ->label('Account Type')
                                        ->options([
                                            'gl_account' => 'G/L Account',
                                            'customer' => 'Customer',
                                            'vendor' => 'Vendor',
                                            'bank_account' => 'Bank Account',
                                            'fixed_asset' => 'Fixed Asset',
                                            'employee' => 'Employee',
                                        ])
                                        ->default('gl_account')
                                        ->required()
                                        ->native(false)
                                        ->live(),

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
                                        ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->no} - {$record->name}")
                                        ->helperText('Leave blank if the batch has a default balancing account.'),

                                    TextInput::make('business_unit_id')
                                        ->label('Business Unit')
                                        ->maxLength(20),
                                ]),
                            ]),

                        Tabs\Tab::make('Amount')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('debit_amount')
                                        ->label('Debit Amount')
                                        ->numeric()
                                        ->default(0)
                                        ->minValue(0)
                                        ->live()
                                        ->afterStateUpdated(function ($state, $set) {
                                            if ((float) $state > 0) {
                                                $set('credit_amount', 0);
                                            }
                                        }),

                                    TextInput::make('credit_amount')
                                        ->label('Credit Amount')
                                        ->numeric()
                                        ->default(0)
                                        ->minValue(0)
                                        ->live()
                                        ->afterStateUpdated(function ($state, $set) {
                                            if ((float) $state > 0) {
                                                $set('debit_amount', 0);
                                            }
                                        }),

                                    TextInput::make('amount_lcy')
                                        ->label('Amount (LCY)')
                                        ->numeric()
                                        ->readOnly(),
                                ]),

                                Grid::make(3)->schema([
                                    TextInput::make('currency_code')
                                        ->label('Currency Code')
                                        ->maxLength(10),

                                    TextInput::make('currency_factor')
                                        ->label('Exchange Rate')
                                        ->numeric()
                                        ->default(1),

                                    TextInput::make('amount_currency')
                                        ->label('Amount (Currency)')
                                        ->numeric()
                                        ->default(0),
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
                                        ->maxLength(10),

                                    TextInput::make('reason_code')
                                        ->label('Reason Code')
                                        ->maxLength(10),

                                    Textarea::make('comment')
                                        ->rows(2)
                                        ->columnSpanFull(),
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

                TextColumn::make('posting_date')
                    ->label('Posting Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('document_type')
                    ->label('Doc. Type')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),

                TextColumn::make('document_no')
                    ->label('Doc. No.')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('account.name')
                    ->label('Account')
                    ->searchable()
                    ->limit(25),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(30)
                    ->placeholder('—'),

                TextColumn::make('debit_amount')
                    ->label('Debit')
                    ->numeric(decimalPlaces: 2)
                    ->alignment('right')
                    ->color('success')
                    ->placeholder('—'),

                TextColumn::make('credit_amount')
                    ->label('Credit')
                    ->numeric(decimalPlaces: 2)
                    ->alignment('right')
                    ->color('danger')
                    ->placeholder('—'),

                TextColumn::make('shortcut_dimension_1_code')
                    ->label('Dim 1')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('shortcut_dimension_2_code')
                    ->label('Dim 2')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('line_status')
                    ->badge()
                    ->color(fn (JournalLineStatus $state) => match ($state) {
                        JournalLineStatus::OPEN => 'info',
                        JournalLineStatus::CHECKED => 'warning',
                        JournalLineStatus::POSTED => 'success',
                        JournalLineStatus::REJECTED => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('line_status')
                    ->label('Status')
                    ->options(JournalLineStatus::class)
                    ->native(false),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Line')
                    ->icon('heroicon-o-plus-circle')
                    ->mutateFormDataUsing(function (array $data) {
                        if (empty($data['line_no'])) {
                            // Auto-increment line_no: find max + 10000
                            $max = $this->getOwnerRecord()->lines()->max('line_no') ?? 0;
                            $data['line_no'] = $max + 10000;
                        }

                        $data['line_status'] = JournalLineStatus::OPEN->value;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(fn ($record) => $record->line_status === JournalLineStatus::POSTED),
                DeleteAction::make()
                    ->hidden(fn ($record) => $record->line_status === JournalLineStatus::POSTED),
            ])
            ->reorderable('line_no')
            ->defaultSort('line_no');
    }
}
