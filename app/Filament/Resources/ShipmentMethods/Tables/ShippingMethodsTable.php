<?php

namespace App\Filament\Resources\ShipmentMethods\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ShippingMethodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->transport_mode),
                TextColumn::make('incoterm_code')
                    ->label('Incoterm')
                    ->badge()
                    ->color('info')
                    ->placeholder('N/A'),
                IconColumn::make('seller_pays_freight')
                    ->label('Freight')
                    ->boolean()
                    ->tooltip('Seller Pays Freight')
                    ->alignCenter(),
                IconColumn::make('seller_pays_insurance')
                    ->label('Insurance')
                    ->boolean()
                    ->tooltip('Seller Pays Insurance')
                    ->alignCenter(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
                IconColumn::make('blocked')
                    ->label('Blocked')
                    ->boolean()
                    ->trueColor('danger')
                    ->alignCenter(),
                TextColumn::make('defaultShippingAgent.name')
                    ->label('Default Agent')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('code')
            ->filters([
                SelectFilter::make('transport_mode')
                    ->options([
                        'AIR' => 'Air',
                        'OCEAN' => 'Ocean',
                        'ROAD' => 'Road',
                        'RAIL' => 'Rail',
                    ]),
                TernaryFilter::make('is_incoterm')
                    ->label('Incoterms Only'),
                TernaryFilter::make('is_active')
                    ->label('Active Status'),
                TernaryFilter::make('blocked')
                    ->label('Blocked Status'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
