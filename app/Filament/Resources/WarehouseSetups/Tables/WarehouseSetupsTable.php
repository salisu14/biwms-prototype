<?php

namespace App\Filament\Resources\WarehouseSetups\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WarehouseSetupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('location_mandatory')
                    ->label('Loc. Mandatory')
                    ->boolean()
                    ->trueColor('primary')
                    ->tooltip('Requires a Location Code on all journal and document lines.'),

                IconColumn::make('bin_mandatory')
                    ->label('Bin Mandatory')
                    ->boolean()
                    ->trueColor('primary')
                    ->tooltip('Requires a Bin Code on all warehouse and item ledger entries.'),

                IconColumn::make('directed_putaway_and_pick')
                    ->label('Directed')
                    ->boolean()
                    ->trueIcon('heroicon-s-cpu-chip')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->color('danger')
                    ->tooltip('Advanced Warehousing: Enables bin ranking, capacity, and FEFO.'),

                IconColumn::make('pick_according_to_fefo')
                    ->label('FEFO Pick')
                    ->boolean()
                    ->trueIcon('heroicon-s-clock')
                    ->color('warning')
                    ->toggleable(),

                IconColumn::make('require_receive')
                    ->label('Req. Receive')
                    ->boolean()
                    ->color('info')
                    ->toggleable(),

                IconColumn::make('require_putaway')
                    ->label('Req. Put-away')
                    ->boolean()
                    ->color('info')
                    ->toggleable(),

                IconColumn::make('require_shipment')
                    ->label('Req. Ship')
                    ->boolean()
                    ->color('success')
                    ->toggleable(),

                IconColumn::make('require_pick')
                    ->label('Req. Pick')
                    ->boolean()
                    ->color('success')
                    ->toggleable(),

                TextColumn::make('bin_capacity_policy')
                    ->label('Bin Policy')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                TextColumn::make('default_bin_selection')
                    ->label('Default Bin Selection')
                    ->description(fn ($record) => $record->putaway_template_nos ? "Template: {$record->putaway_template_nos}" : null)
                    ->toggleable(),

                TextColumn::make('warehouse_receipt_nos')
                    ->label('Receipt Nos.')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('warehouse_shipment_nos')
                    ->label('Shipment Nos.')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('internal_putaway_nos')
                    ->label('Internal Put-away Nos.')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('internal_pick_nos')
                    ->label('Internal Pick Nos.')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Last Modified')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
