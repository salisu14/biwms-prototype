<?php

namespace App\Filament\Resources\FAJournalBatches\RelationManagers;

use App\Enums\FAPostingType;
use App\Filament\Resources\FAJournalBatches\FAJournalBatchResource;
use App\Models\FixedAsset;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $relatedResource = FAJournalBatchResource::class;

    protected static ?string $recordTitleAttribute = 'description';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)->schema([
                    TextInput::make('line_no')
                        ->label('Line No.')
                        ->numeric()
                        ->required()
                        ->default(fn ($livewire) => ($livewire->ownerRecord->lines()->max('line_no') ?? 0) + 10),

                    DatePicker::make('posting_date')
                        ->label('Posting Date')
                        ->required()
                        ->default(fn ($livewire) => $livewire->ownerRecord->posting_date ?? now())
                        ->native(false),

                    TextInput::make('document_no')
                        ->label('Document No.')
                        ->required()
                        ->maxLength(50),
                ]),

                Grid::make(2)->schema([
                    Select::make('fixed_asset_id')
                        ->label('Fixed Asset')
                        ->relationship('fixedAsset', 'fa_no')
                        ->getOptionLabelFromRecordUsing(fn (FixedAsset $record) => "{$record->fa_no} – {$record->description}")
                        ->searchable()
                        ->preload()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state, $set) {
                            if (!$state) return;
                            $asset = FixedAsset::find($state);
                            if ($asset) {
                                $set('description', $asset->description);
                                $set('fa_no', $asset->fa_no);
                                $set('fa_posting_group_id', $asset->fa_posting_group_id);
                            }
                        }),

                    Select::make('fa_posting_type')
                        ->label('Posting Type')
                        ->options(FAPostingType::class)
                        ->required()
                        ->native(false),

                    TextInput::make('description')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),

                Grid::make(3)->schema([
                    TextInput::make('amount')
                        ->numeric()
                        ->prefix('$')
                        ->required()
                        ->step(0.0001)
                        ->helperText('Value of the transaction (Acquisition, Depr, etc.)'),

                    TextInput::make('number_of_depreciation_days')
                        ->label('Depr. Days')
                        ->numeric()
                        ->placeholder('Optional'),

                    Toggle::make('calculate_depreciation')
                        ->label('Calc. Depr')
                        ->default(fn ($livewire) => $livewire->ownerRecord->calculate_depreciation)
                        ->inline(false),
                ]),

                TextInput::make('fa_no')->hidden()->dehydrated(),
                TextInput::make('fa_posting_group_id')->hidden()->dehydrated(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('line_no')
                    ->label('Line')
                    ->sortable(),

                TextColumn::make('fixedAsset.fa_no')
                    ->label('Asset No.')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('description')
                    ->limit(30),

                TextColumn::make('fa_posting_type')
                    ->label('Type')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('amount')
                    ->money()
                    ->alignment('right')
                    ->weight('bold'),

                TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Asset Transaction'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('line_no', 'asc');
    }
}
