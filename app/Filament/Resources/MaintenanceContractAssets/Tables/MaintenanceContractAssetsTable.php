<?php

namespace App\Filament\Resources\MaintenanceContractAssets\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MaintenanceContractAssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('maintenanceContract.contract_no')
                    ->label('Contract')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->maintenanceContract?->description)
                    ->weight('bold'),

                TextColumn::make('fixedAsset.fa_no')
                    ->label('Fixed Asset')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->fixedAsset?->description),

                TextColumn::make('covered_serial_no')
                    ->label('Serial No.')
                    ->searchable()
                    ->copyable()
                    ->default('—'),

                TextColumn::make('asset_specific_limit')
                    ->label('Coverage Limit')
                    ->formatStateUsing(fn ($state) => $state === null ? 'Unlimited' : number_format((float) $state, 2))
                    ->sortable()
                    ->alignEnd()
                    ->color(fn ($state) => $state !== null ? 'primary' : 'gray'),

                TextColumn::make('special_conditions')
                    ->label('Conditions')
                    ->limit(40)
                    ->toggleable()
                    ->tooltip(fn ($record) => $record->special_conditions),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('maintenance_contract_id')
                    ->label('Contract')
                    ->relationship('maintenanceContract', 'contract_no')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('fixed_asset_id')
                    ->label('Fixed Asset')
                    ->relationship('fixedAsset', 'fa_no')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
