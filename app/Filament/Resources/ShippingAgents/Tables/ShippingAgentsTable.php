<?php

namespace App\Filament\Resources\ShippingAgents\Tables;

use App\Enums\ShippingAgentServiceType;
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

class ShippingAgentsTable
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
                TextColumn::make('name')
                    ->label('Agent Name')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->city . ($record->country_code ? ", {$record->country_code}" : "")),
                TextColumn::make('default_service_type')
                    ->label('Default Service')
                    ->badge()
                    ->sortable(),
                TextColumn::make('account_no')
                    ->label('Account #')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('base_charge')
                    ->money()
                    ->sortable()
                    ->alignment('right'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
                IconColumn::make('blocked')
                    ->label('Blocked')
                    ->boolean()
                    ->trueColor('danger')
                    ->alignCenter(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone_no')
                    ->label('Phone')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('code')
            ->filters([
                SelectFilter::make('default_service_type')
                    ->options(ShippingAgentServiceType::class),
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
