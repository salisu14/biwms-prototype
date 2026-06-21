<?php

namespace App\Filament\Resources\Currencies\RelationManagers;

use App\Enums\CurrencyExchangeRateType;
use App\Filament\Resources\Currencies\CurrencyResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
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

class ExchangeRatesRelationManager extends RelationManager
{
    protected static string $relationship = 'exchangeRates';

    protected static ?string $title = 'Exchange Rates';

    protected static ?string $relatedResource = CurrencyResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Rate Definition')
                    ->schema([
                        Grid::make(2)->schema([
                            DatePicker::make('starting_date')
                                ->label('Starting Date')
                                ->default(now())
                                ->required(),

                            DatePicker::make('ending_date')
                                ->label('Ending Date')
                                ->helperText('Leave blank if this is the current active rate.'),
                        ]),
                        Grid::make(3)->schema([
                            TextInput::make('exchange_rate_amount')
                                ->label('Exchange Rate')
                                ->numeric()
                                ->step(0.000001)
                                ->required(),

                            Select::make('rate_type')
                                ->options(CurrencyExchangeRateType::class)
                                ->default(CurrencyExchangeRateType::SPOT)
                                ->required(),

                            Toggle::make('is_current')
                                ->label('Set as Current')
                                ->default(true)
                                ->helperText('Activating this will automatically close the previous current rate.'),
                        ]),
                    ]),
                Section::make('Source Information')
                    ->collapsed()
                    ->schema([
                        TextInput::make('source')
                            ->placeholder('e.g., Central Bank API'),

                        TextInput::make('source_reference')
                            ->placeholder('e.g., Batch #1234'),
                    ])->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('starting_date')
            ->columns([
                TextColumn::make('starting_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('ending_date')
                    ->date()
                    ->placeholder('Active')
                    ->sortable(),

                TextColumn::make('exchange_rate_amount')
                    ->label('Rate')
                    ->numeric(decimalPlaces: 6)
                    ->sortable(),

                TextColumn::make('rate_type')
                    ->badge(),

                IconColumn::make('is_current')
                    ->label('Current')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('source')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('starting_date', 'desc')
            ->filters([
                SelectFilter::make('rate_type')
                    ->options(CurrencyExchangeRateType::class),
                TernaryFilter::make('is_current')
                    ->label('Current Rate Only'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->icon('heroicon-m-plus-circle'),
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
