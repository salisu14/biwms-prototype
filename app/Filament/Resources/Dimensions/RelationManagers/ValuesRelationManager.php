<?php

namespace App\Filament\Resources\Dimensions\RelationManagers;

use App\Enums\DimensionValueType;
use App\Filament\Resources\Dimensions\DimensionResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'values';

    protected static ?string $relatedResource = DimensionResource::class;

    protected static ?string $title = 'Dimension Values';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('code')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(20),
                    TextInput::make('name')
                        ->required()
                        ->maxLength(100),
                ]),
                Grid::make(2)->schema([
                    Select::make('dimension_value_type')
                        ->label('Value Type')
                        ->options(DimensionValueType::class)
                        ->default(DimensionValueType::Standard)
                        ->required(),
                    Select::make('parent_id')
                        ->label('Parent Value')
                        ->relationship('parent', 'name', fn ($query) => $query->where('dimension_id', $this->getOwnerRecord()->id))
                        ->searchable()
                        ->placeholder('Root Level'),
                ]),
                Section::make('Availability')
                    ->schema([
                        Grid::make(2)->schema([
                            DatePicker::make('starting_date'),
                            DatePicker::make('ending_date'),
                        ]),
                        Toggle::make('blocked')
                            ->label('Blocked')
                            ->default(false),
                    ])->compact(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    // Visually represent hierarchy with indentation
                    ->formatStateUsing(fn ($record, $state) => str_repeat('— ', $record->indentation).$state),
                TextColumn::make('dimension_value_type')
                    ->label('Type')
                    ->badge(),
                IconColumn::make('blocked')
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('starting_date')
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('code')
            ->filters([
                TernaryFilter::make('blocked'),
                SelectFilter::make('dimension_value_type')
                    ->options(DimensionValueType::class),
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
