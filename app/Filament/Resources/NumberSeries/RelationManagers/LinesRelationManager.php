<?php

namespace App\Filament\Resources\NumberSeries\RelationManagers;

use App\Filament\Resources\NumberSeries\NumberSeriesResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $relatedResource = NumberSeriesResource::class;

    protected static ?string $recordTitleAttribute = 'no_series_code';

    protected static ?string $title = 'Series Lines';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)
                    ->schema([
                        DatePicker::make('starting_date')
                            ->label('Starting Date')
                            ->required()
                            ->default(now()->startOfYear())
                            ->native(false)
                            ->columnSpan(1),

                        Toggle::make('blocked')
                            ->label('Blocked')
                            ->inline(false)
                            ->default(false)
                            ->columnSpan(1),
                    ]),

                Grid::make(4)
                    ->schema([
                        TextInput::make('prefix')
                            ->label('Prefix')
                            ->maxLength(20)
                            ->placeholder('e.g., INV-')
                            ->helperText('Prepended to the number.'),

                        TextInput::make('suffix')
                            ->label('Suffix')
                            ->maxLength(20)
                            ->placeholder('e.g., /24')
                            ->helperText('Appended to the number.'),

                        TextInput::make('no_of_digits')
                            ->label('Number of Digits')
                            ->required()
                            ->numeric()
                            ->default(5)
                            ->minValue(1)
                            ->maxValue(10)
                            ->helperText('e.g., 5 generates 00001'),

                        TextInput::make('increment_by')
                            ->label('Increment By')
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->minValue(1),
                    ]),

                Grid::make(4)
                    ->schema([
                        TextInput::make('starting_no')
                            ->label('Starting No.')
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->minValue(0)
                            ->helperText('The first number in the sequence.'),

                        TextInput::make('ending_no')
                            ->label('Ending No.')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Leave empty for unlimited.'),

                        TextInput::make('last_no_used')
                            ->label('Last No. Used')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Current sequence position. Set to 0 to start from the beginning.'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('starting_date')
                    ->label('Starts On')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('preview')
                    ->label('Next Number Preview')
                    ->state(function ($record) {
                        // Use the model's method to get the formatted next number
                        return $record->getNextFullNumber() ?? 'BLOCKED / EXHAUSTED';
                    })
                    ->badge()
                    ->color(fn ($record) => $record->blocked ? 'danger' : 'success'),

                TextColumn::make('range')
                    ->label('Number Range')
                    ->state(fn ($record) => number_format($record->starting_no) . ' → ' . ($record->ending_no ? number_format($record->ending_no) : '∞')),

                TextColumn::make('last_no_used')
                    ->label('Last Used')
                    ->numeric()
                    ->sortable(),

                IconColumn::make('blocked')
                    ->label('Blocked')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('starting_date', 'desc')
            ->filters([
                TernaryFilter::make('blocked')
                    ->label('Status')
                    ->trueLabel('Blocked Only')
                    ->falseLabel('Active Only'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data, $livewire): array {
                        // Automatically inject the series code from the parent record
                        $data['no_series_code'] = $livewire->ownerRecord->code;
                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
