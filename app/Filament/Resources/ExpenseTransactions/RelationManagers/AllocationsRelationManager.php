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
                Select::make('target_gl_account_id')
                    ->label('Target Account')
                    ->relationship('targetAccount', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                TextInput::make('allocation_basis')
                    ->placeholder('e.g. Sales, Headcount')
                    ->maxLength(50),

                TextInput::make('allocation_percentage')
                    ->numeric()
                    ->label('Allocation %')
                    ->suffix('%')
                    ->maxValue(100)
                    ->minValue(0),

                TextInput::make('allocated_amount')
                    ->numeric()
                    ->required()
                    ->prefix('$'),

                Select::make('dimension_set_id')
                    ->label('Target Dimension Set')
                    ->relationship('dimensionSet', 'id')
                    ->searchable()
                    ->preload(),

                Select::make('target_dimension_1')
                    ->label('Target Dept (Dim 1)')
                    ->options(fn () => DimensionValue::query()
                        ->whereHas('dimension', fn ($q) => $q->where('code', 'DEPARTMENT'))
                        ->pluck('name', 'code')
                        ->toArray()
                    )
                    ->searchable()
                    ->preload(),

                Select::make('target_dimension_2')
                    ->label('Target Project (Dim 2)')
                    ->options(fn () => DimensionValue::query()
                        ->where('code', 'PROJECT')
                        ->pluck('name', 'code')
                        ->toArray()
                    )
                    ->searchable()
                    ->preload(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('targetAccount.name')
                    ->label('Target G/L'),

                TextColumn::make('allocation_percentage')
                    ->label('%')
                    ->suffix('%'),

                TextColumn::make('allocated_amount')
                    ->money()
                    ->label('Amount'),

                TextColumn::make('dimension_set_id')
                    ->label('Dim Set'),

                TextColumn::make('target_dimension_1')
                    ->label('Dim 1'),

                TextColumn::make('target_dimension_2')
                    ->label('Dim 2'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
