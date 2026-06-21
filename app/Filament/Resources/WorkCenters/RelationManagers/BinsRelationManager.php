<?php

namespace App\Filament\Resources\WorkCenters\RelationManagers;

use App\Enums\FlushingMethod;
use App\Filament\Resources\WorkCenters\WorkCenterResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BinsRelationManager extends RelationManager
{
    protected static string $relationship = 'bins';

    protected static ?string $relatedResource = WorkCenterResource::class;

    protected static ?string $title = 'Shop Floor Bin Setup';

    protected static ?string $modelLabel = 'Bin Configuration';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Consumption Strategy')
                    ->description('Configuration for materials entering the work center.')
                    ->schema([
                        Grid::make(2)->schema([
                            self::getBinSelect('open_shop_floor_bin_id', 'openShopFloorBin', 'Open Shop Floor Bin'),
                            self::getBinSelect('to_production_bin_id', 'toProductionBin', 'To-Production Bin'),
                        ]),
                        Select::make('flushing_method')
                            ->label('Consumption (Flushing) Method')
                            ->options(FlushingMethod::class)
                            ->required()
                            ->native(false)
                            ->helperText('Determines when inventory is physically deducted from the bins.'),
                    ]),

                Section::make('Output Strategy')
                    ->description('Configuration for finished or semi-finished items leaving the work center.')
                    ->schema([
                        Grid::make(2)->schema([
                            self::getBinSelect('from_production_bin_id', 'fromProductionBin', 'From-Production Bin'),
                            self::getBinSelect('fixed_bin_id', 'fixedBin', 'Fixed Bin (Output)'),
                        ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('flushing_method')
            ->columns([
                TextColumn::make('flushing_method')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('openShopFloorBin.bin_code')
                    ->label('Open Shop Floor')
                    ->description(fn ($record) => $record->openShopFloorBin?->bin_name)
                    ->placeholder('Not Configured')
                    ->toggleable(),

                TextColumn::make('toProductionBin.bin_code')
                    ->label('To-Prod Bin')
                    ->placeholder('N/A'),

                TextColumn::make('fromProductionBin.bin_code')
                    ->label('From-Prod Bin')
                    ->placeholder('N/A'),

                IconColumn::make('requires_pick')
                    ->label('Pick Doc?')
                    ->state(fn ($record) => $record->requiresPickDocument())
                    ->boolean()
                    ->alignCenter()
                    ->tooltip('Based on flushing method logic'),
            ])
            ->filters([
                SelectFilter::make('flushing_method')
                    ->options(FlushingMethod::class),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Initialize Bin Links')
                    ->icon('heroicon-m-plus')
                    // Prevent creating multiple configurations for a single work center
                    ->hidden(fn (RelationManager $livewire) => $livewire->getOwnerRecord()->bins()->exists()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * Standardized Bin Selector
     */
    protected static function getBinSelect(string $name, string $relationship, string $label): Select
    {
        return Select::make($name)
            ->label($label)
            ->relationship($relationship, 'bin_code')
            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->bin_code} - {$record->bin_name}")
            ->searchable()
            ->preload();
    }
}
