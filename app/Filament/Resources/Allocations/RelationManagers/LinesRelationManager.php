<?php

namespace App\Filament\Resources\Allocations\RelationManagers;

use App\Filament\Resources\Allocations\AllocationResource;
use App\Models\ChartOfAccount;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $relatedResource = AllocationResource::class;

    protected static ?string $recordTitleAttribute = 'description';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(2)->schema([
                    Select::make('target_account_id')
                        ->label('Target G/L Account')
                        ->relationship('targetAccount', 'account_number')
                        ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->account_number} – {$record->name}")
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpanFull(),

                    TextInput::make('percentage')
                        ->label('Allocation %')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->maxValue(100)
                        ->suffix('%')
                        ->placeholder('e.g., 25.00'),

                    TextInput::make('description')
                        ->label('Line Description')
                        ->placeholder('Optional breakdown detail')
                        ->maxLength(255),
                ]),

                Grid::make(2)->schema([
                    TextInput::make('shortcut_dimension_1_code')
                        ->label('Dimension 1')
                        ->placeholder('e.g. DEPT-01'),

                    TextInput::make('shortcut_dimension_2_code')
                        ->label('Dimension 2')
                        ->placeholder('e.g. PROJ-X'),
                ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('targetAccount.account_number')
                    ->label('G/L Account')
                    ->description(fn ($record) => $record->targetAccount?->name)
                    ->sortable()
                    ->searchable(),

                TextColumn::make('percentage')
                    ->label('Split %')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->weight('bold')
                    ->alignment('right'),

                TextColumn::make('shortcut_dimension_1_code')
                    ->label('Dim 1')
                    ->toggleable(),

                TextColumn::make('shortcut_dimension_2_code')
                    ->label('Dim 2')
                    ->toggleable(),

                TextColumn::make('description')
                    ->label('Note')
                    ->limit(30)
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Distribution Line'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('percentage', 'desc');
    }
}
