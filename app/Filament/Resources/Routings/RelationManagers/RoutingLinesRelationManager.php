<?php

namespace App\Filament\Resources\Routings\RelationManagers;

use App\Filament\Resources\Routings\RoutingResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoutingLinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $relatedResource = RoutingResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(4)->schema([
                    TextInput::make('operation_no')
                        ->label('Operation No.')
                        ->numeric()
                        ->required()
                        ->default(fn ($record) => $record ? $record->operation_no : (static::getNextOperationNumber($this->getOwnerRecord()->lines->max('operation_no') ?? 0))),

                    TextInput::make('description')
                        ->label('Description')
                        ->required()
                        ->columnSpan(2),

                    Select::make('type') // Assuming RoutingLine has a type
                    ->label('Type')
                        ->options([
                            'MACHINE' => 'Machine Center',
                            'MANUAL' => 'Manual',
                            'VENDOR' => 'Vendor',
                        ])
                        ->default('MANUAL'),

                    Toggle::make('blocking')
                        ->label('Blocking')
                        ->default(false),
            ]),

                Section::make('Times & Costs')
                    ->description('Setup and run times for this operation.')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('setup_time')
                                ->label('Setup Time')
                                ->numeric()
                                ->suffix('mins')
                                ->default(0),

                            TextInput::make('run_time')
                                ->label('Run Time')
                                ->numeric()
                                ->suffix('mins')
                                ->required(),

                            TextInput::make('wait_time')
                                ->label('Wait Time')
                                ->numeric()
                                ->suffix('mins')
                                ->default(0),

                            TextInput::make('move_time')
                                ->label('Move Time')
                                ->numeric()
                                ->suffix('mins')
                                ->default(0),

                            TextInput::make('cost')
                                ->label('Cost')
                                ->numeric()
                                ->prefix('$')
                                ->default(0),
                        ]),
                    ]),

                Section::make('Work Center & Next Operations')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('work_center_id')
                                ->label('Work Center')
                                ->relationship(name: 'workCenter', titleAttribute: 'name')
                                ->searchable()
                                ->preload()
                                ->required(),

                            TextInput::make('next_operation_code')
                                ->label('Next Operation Code')
                                ->helperText('Code of the next routing link'),
                        ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('operation_no', 'asc')
            ->columns([
                TextColumn::make('operation_no')
                    ->label('No.')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'MACHINE' => 'primary',
                        'MANUAL' => 'warning',
                        'VENDOR' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('work_center.name')
                    ->label('Work Center')
                    ->toggleable(),

                TextColumn::make('setup_time')
                    ->label('Setup')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('run_time')
                    ->label('Run Time')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('cost')
                    ->money('USD')
                    ->toggleable(),

                IconColumn::make('blocking')
                    ->label('Blocking')
                    ->boolean()
                    ->trueIcon('heroicon-o-stop')
                    ->falseIcon('heroicon-o-arrow-right')
                    ->trueColor('danger')
                    ->falseColor('success'),
            ])
            ->headerActions([
                CreateAction::make(),
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

    /**
     * Helper to get the next operation number
     */
    protected static function getNextOperationNumber(int $maxSoFar): int
    {
        return $maxSoFar + 10000;
    }
}
