<?php

namespace App\Filament\Resources\ExpenseTransactions\RelationManagers;

use App\Filament\Resources\ExpenseTransactions\ExpenseTransactionResource;
use App\Models\DimensionValue;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AllocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'allocations';

    //    protected static ?string $relatedResource = ExpenseTransactionResource::class;

    protected static ?string $recordTitleAttribute = 'allocated_amount';

    protected static ?string $title = 'Expense Allocation';

    protected static ?string $inverseRelationship = 'expenseTransaction';

    protected static ?string $inverseRelationshipTitle = 'Expense Transaction';

    protected static ?string $inverseRecordTitleAttribute = 'expense_transaction_id';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Allocation Details')
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('target_gl_account_id')
                                ->label('Target Account')
                                ->relationship('targetAccount', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),

                            Select::make('allocation_type')
                                ->label('Type')
                                ->options([
                                    'percentage' => 'Percentage',
                                    'amount' => 'Fixed Amount',
                                ])
                                ->default('percentage')
                                ->required()
                                ->live(),

                            TextInput::make('allocation_basis')
                                ->label('Allocation Basis')
                                ->placeholder('e.g. Sales, Headcount')
                                ->maxLength(50)
                                ->columnSpan(1),
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make('allocation_percentage')
                                ->label('Allocation Percentage')
                                ->numeric()
                                ->suffix('%')
                                ->maxValue(100)
                                ->minValue(0)
                                ->step(0.01)
                                ->visible(fn ($get) => $get('allocation_type') === 'percentage')
                                ->required(fn ($get) => $get('allocation_type') === 'percentage'),

                            TextInput::make('allocated_amount')
                                ->label('Allocated Amount')
                                ->numeric()
                                ->prefix('$')
                                ->step(0.0001)
                                ->visible(fn ($get) => $get('allocation_type') === 'amount')
                                ->required(fn ($get) => $get('allocation_type') === 'amount'),

                            Select::make('dimension_set_id')
                                ->label('Target Dimension Set')
                                ->relationship('dimensionSet', 'id')
                                ->searchable()
                                ->preload(),

                            Select::make('gl_entry_id')
                                ->label('G/L Entry')
                                ->relationship('glEntry', 'entry_number')
                                ->searchable()
                                ->preload()
                                ->disabled() // Usually set during posting, not manual entry
                                ->dehydrated(false),
                        ]),
                    ]),

                Section::make('Dimensions')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('target_dimension_1')
                                ->label('Department (Dim 1)')
                                ->options(function () {
                                    return DimensionValue::query()
                                        ->whereHas('dimension', fn ($q) => $q->where('code', 'DEPARTMENT'))
                                        ->pluck('name', 'code')
                                        ->toArray();
                                })
                                ->searchable()
                                ->preload(),

                            Select::make('target_dimension_2')
                                ->label('Project (Dim 2)')
                                ->options(function () {
                                    return DimensionValue::query()
                                        ->whereHas('dimension', fn ($q) => $q->where('code', 'PROJECT'))
                                        ->pluck('name', 'code')
                                        ->toArray();
                                })
                                ->searchable()
                                ->preload(),
                        ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('targetAccount.name')
                    ->label('Target G/L')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('allocation_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'amount' => 'success',
                        'percentage' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('allocation_percentage')
                    ->label('%')
                    ->suffix('%')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('-'),

                TextColumn::make('allocated_amount')
                    ->money('NGN')
                    ->label('Amount')
                    ->placeholder('-'),

                TextColumn::make('allocation_basis')
                    ->label('Basis')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('target_dimension_1')
                    ->label('Dim 1 (Dept)'),

                TextColumn::make('target_dimension_2')
                    ->label('Dim 2 (Proj)'),

                TextColumn::make('glEntry.entry_number')
                    ->label('G/L Entry')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        // FIX: Ensure non-visible fields are not null to satisfy DB constraints
                        if (($data['allocation_type'] ?? 'percentage') === 'percentage') {
                            $data['allocated_amount'] = 0;
                        } else {
                            $data['allocation_percentage'] = 0;
                        }
                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        // Ensure non-visible fields are not null during Edit
                        if (($data['allocation_type'] ?? 'percentage') === 'percentage') {
                            $data['allocated_amount'] = 0;
                        } else {
                            $data['allocation_percentage'] = 0;
                        }
                        return $data;
                    }),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
