<?php

namespace App\Filament\Resources\Assets\Tables;

use App\Enums\AssetType;
use App\Enums\FixedAssetCategory;
use App\Enums\LiquidityAssetType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('asset_no')
                    ->label('Asset No.')
                    ->weight('bold')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('description')
                    ->searchable()
                    ->description(fn ($record) => $record?->description_2),

                TextColumn::make('asset_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        AssetType::FIXED => 'info',
                        AssetType::LIQUIDITY => 'success',
                        default => 'gray',
                    })
                    ->description(function ($record) {
                        if ($record?->asset_type === AssetType::FIXED) {
                            return $record->fixed_asset_category?->label().' - '.
                                ($record->tangible_type?->label() ?? $record->intangible_type?->label() ?? 'Uncategorized');
                        }

                        return $record?->liquidity_type?->label();
                    }),

                TextColumn::make('current_balance')
                    ->label('Value / Balance')
                    ->state(fn ($record) => $record?->isFixedAsset() ? $record->book_value : $record->current_balance)
                    ->money('USD') // Ideally dynamic based on record->currency->code
                    ->sortable()
                    ->alignment('right'),

                IconColumn::make('active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),

                IconColumn::make('acquired')
                    ->label('Acq.')
                    ->boolean()
                    ->visible(fn ($record) => $record?->isFixedAsset() ?? true)
                    ->alignCenter(),

                TextColumn::make('fa_location_code')
                    ->label('Location')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('asset_no')
            ->filters([
                SelectFilter::make('asset_type')
                    ->options(AssetType::class),
                SelectFilter::make('fixed_asset_category')
                    ->options(FixedAssetCategory::class),
                SelectFilter::make('liquidity_type')
                    ->options(LiquidityAssetType::class),
                TernaryFilter::make('active')
                    ->label('Active Assets'),
            ])
            ->recordActions([
                EditAction::make(),
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
