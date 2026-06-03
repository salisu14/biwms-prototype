<?php

namespace App\Filament\Resources\ExpenseTransactions\RelationManagers;

use App\Models\ChartOfAccount;
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

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $title = 'Expense Allocations (Splits)';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Allocation Details')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('title')
                                ->label('Split Title')
                                ->placeholder('e.g. Digital Marketing')
                                ->required()
                                ->maxLength(120),

                            Select::make('target_gl_account_id')
                                ->label('Target Account')
                                ->relationship('targetAccount', 'account_number')
                                ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->account_number} – {$record->name}")
                                ->searchable()
                                ->preload()
                                ->required(),

                            Select::make('allocation_type')
                                ->label('Split Type')
                                ->options([
                                    'percentage' => 'Percentage (%)',
                                    'amount' => 'Fixed Amount ($)',
                                ])
                                ->default('percentage')
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    $set('allocated_amount', 0);
                                    $set('allocation_percentage', 0);
                                }),

                            TextInput::make('allocation_basis')
                                ->label('Basis/Reason')
                                ->placeholder('e.g. Sales Split')
                                ->maxLength(50),
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
                                ->required(fn ($get) => $get('allocation_type') === 'percentage')
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, $set) {
                                    $parentAmount = (float) $this->getOwnerRecord()->amount;
                                    $set('allocated_amount', round($parentAmount * ($state / 100), 4));
                                }),

                            TextInput::make('allocated_amount')
                                ->label('Allocated Amount')
                                ->numeric()
                                ->prefix('₦')
                                ->step(0.0001)
                                ->visible(fn ($get) => $get('allocation_type') === 'amount')
                                ->required(fn ($get) => $get('allocation_type') === 'amount'),
                        ]),
                    ]),

                Section::make('Dimensions')
                    ->description('Assign cost centers for this specific split.')
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
                TextColumn::make('targetAccount.account_number')
                    ->label('Account')
                    ->description(fn ($record) => $record->targetAccount?->name)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('title')
                    ->label('Split')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('allocation_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'amount' => 'success',
                        'percentage' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('allocation_percentage')
                    ->label('%')
                    ->suffix('%')
                    ->numeric(decimalPlaces: 2)
                    ->alignment('right'),

                TextColumn::make('allocated_amount')
                    ->money('NGN')
                    ->label('Amount')
                    ->weight('bold')
                    ->alignment('right'),

                TextColumn::make('target_dimension_1')
                    ->label('Dept')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('target_dimension_2')
                    ->label('Project')
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        if (empty($data['title']) && ! empty($data['allocation_basis'])) {
                            $data['title'] = $data['allocation_basis'];
                        }

                        if (($data['allocation_type'] ?? 'percentage') === 'percentage') {
                            $parentAmount = (float) $this->getOwnerRecord()->amount;
                            $data['allocated_amount'] = round($parentAmount * (($data['allocation_percentage'] ?? 0) / 100), 4);
                        }

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
