<?php

namespace App\Filament\Resources\ItemTrackingCodes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemTrackingCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->wrap(),

                // Visual indicators of what this code actually does
                IconColumn::make('snspecific_tracking')
                    ->label('SN Track')
                    ->boolean()
                    ->trueIcon('heroicon-s-check-circle')
                    ->falseIcon('heroicon-o-minus')
                    ->color('primary'),

                IconColumn::make('lotspecific_tracking')
                    ->label('Lot Track')
                    ->boolean()
                    ->trueIcon('heroicon-s-check-circle')
                    ->falseIcon('heroicon-o-minus')
                    ->color('success'),

                IconColumn::make('strict_expiration_posting')
                    ->label('Strict Exp.')
                    ->boolean()
                    ->toggleable(),

                // Move the "Wall of Toggles" to the toggleable menu to keep the table clean
                IconColumn::make('man_expiration_date_entry_reqd')->label('Req. Exp Date')->boolean()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('lot_info_purchase_inbound')->label('Lot Purc. In')->boolean()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('sn_info_purchase_inbound')->label('SN Purc. In')->boolean()->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
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
                    DeleteBulkAction::make()->label('Delete Selected'),
                ]),
            ]);
    }
}
