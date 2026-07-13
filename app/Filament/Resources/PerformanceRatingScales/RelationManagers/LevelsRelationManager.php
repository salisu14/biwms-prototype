<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceRatingScales\RelationManagers;

use App\Models\PerformanceRatingScaleLevel;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LevelsRelationManager extends RelationManager
{
    protected static string $relationship = 'levels';

    protected static ?string $title = 'Rating Levels';

    protected static string|null|\BackedEnum $icon = 'heroicon-o-list-bullet';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Level Identification')
                    ->icon('heroicon-o-tag')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('code')
                                    ->label('Level Code')
                                    ->required()
                                    ->maxLength(50)
                                    ->placeholder('e.g. EXCEEDS')
                                    ->prefixIcon('heroicon-m-hashtag'),

                                TextInput::make('name')
                                    ->label('Level Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g. Exceeds Expectations')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                                        if (empty($get('code')) && !empty($state)) {
                                            $set('code', strtoupper(str_replace([' ', '-'], '_', $state)));
                                        }
                                    }),

                                ColorPicker::make('color')
                                    ->label('Display Color')
                                    ->required()
                                    ->default('#3B82F6'),
                            ]),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->maxLength(1000)
                            ->placeholder('Describe what this rating level represents...')
                            ->columnSpanFull(),
                    ]),

                Section::make('Score Range')
                    ->icon('heroicon-o-scale')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('score_from')
                                    ->label('Score From')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->live()
                                    ->suffix('pts')
                                    ->placeholder(fn(RelationManager $livewire): string => 'Min: ' . number_format((float)$livewire->getOwnerRecord()->minimum_score, 4)
                                    )
                                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                                        $to = $get('score_to');
                                        if ($to !== null && (float)$state > (float)$to) {
                                            $set('score_to', $state);
                                        }
                                    }),

                                TextInput::make('score_to')
                                    ->label('Score To')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->live()
                                    ->suffix('pts')
                                    ->placeholder(fn(RelationManager $livewire): string => 'Max: ' . number_format((float)$livewire->getOwnerRecord()->maximum_score, 4)
                                    )
                                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                                        $from = $get('score_from');
                                        if ($from !== null && (float)$state < (float)$from) {
                                            $set('score_from', $state);
                                        }
                                    }),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('numeric_value')
                                    ->label('Numeric Value')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('pts')
                                    ->helperText('Weighted value used in aggregate calculations')
                                    ->placeholder('e.g. 4.5'),

                                TextInput::make('sort_order')
                                    ->label('Sort Order')
                                    ->required()
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->default(fn(RelationManager $livewire): int => $livewire->getOwnerRecord()->levels()->count() + 1
                                    )
                                    ->suffix('position'),
                            ]),
                    ]),

                Section::make('Status')
                    ->icon('heroicon-o-flag')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_passing')
                            ->label('Passing Level')
                            ->inline(false)
                            ->default(true)
                            ->onIcon('heroicon-o-check')
                            ->offIcon('heroicon-o-x-mark')
                            ->hintIcon('heroicon-m-academic-cap')
                            ->hint('Scores at this level count as passing'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->heading('Rating Levels')
            ->description('Define score bands and labels within this scale.')
            ->emptyStateHeading('No levels defined')
            ->emptyStateDescription('Add levels to map score ranges to performance labels.')
            ->emptyStateIcon('heroicon-o-list-bullet')
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->alignCenter()
                    ->width('50px')
                    ->sortable(),

                ColorColumn::make('color')
                    ->label('Color')
                    ->copyable(),

                TextColumn::make('code')
                    ->label('Code')
                    ->sortable()
                    ->searchable()
                    ->weight('font-bold')
                    ->width('120px'),

                TextColumn::make('name')
                    ->label('Level Name')
                    ->sortable()
                    ->searchable()
                    ->weight('font-medium'),

                TextColumn::make('score_range')
                    ->label('Score Range')
                    ->getStateUsing(fn(PerformanceRatingScaleLevel $record): string => number_format((float)$record->score_from, 4)
                        . ' – '
                        . number_format((float)$record->score_to, 4)
                    )
                    ->fontFamily('font-mono')
                    ->alignCenter()
                    ->sortable(['score_from', 'score_to']),

                TextColumn::make('numeric_value')
                    ->label('Weight')
                    ->formatStateUsing(fn(?float $state): string => $state !== null ? number_format($state, 4) : '—'
                    )
                    ->fontFamily('font-mono')
                    ->alignCenter()
                    ->toggleable(),

                IconColumn::make('is_passing')
                    ->label('Passing')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->alignCenter(),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(40)
                    ->tooltip(fn(PerformanceRatingScaleLevel $record): ?string => $record->description)
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->placeholder('—'),
            ])
            ->filters([
                \Filament\Tables\Filters\TernaryFilter::make('is_passing')
                    ->label('Passing Level')
                    ->placeholder('All levels')
                    ->trueLabel('Passing only')
                    ->falseLabel('Failing only'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Level')
                    ->icon('heroicon-m-plus')
                    ->modalHeading('Add Rating Level')
                    ->modalDescription('Define a score band and label for this rating scale.'),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading('Edit Rating Level'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->paginated(false);
    }
}
