<?php

declare(strict_types=1);

namespace App\Filament\Resources\Picks\Tables;

use App\Enums\WarehouseDocumentStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PicksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no')
                    ->label('Pick No.')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (WarehouseDocumentStatus $state): string => match ($state) {
                        WarehouseDocumentStatus::OPEN => 'gray',
                        WarehouseDocumentStatus::RELEASED => 'info',
                        WarehouseDocumentStatus::IN_PROGRESS => 'warning',
                        WarehouseDocumentStatus::COMPLETED => 'success',
                        WarehouseDocumentStatus::CANCELLED => 'danger',
                    })
                    ->sortable(),

                TextColumn::make('location.name')
                    ->label('Location')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('source_no')
                    ->label('Source Doc')
                    ->description(fn ($record) => $record->source_document)
                    ->searchable(),

                TextColumn::make('assignedUser.name')
                    ->label('Assigned To')
                    ->placeholder('Unassigned')
                    ->toggleable(),

                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(WarehouseDocumentStatus::class),
                SelectFilter::make('location_id')
                    ->relationship('location', 'name'),
                SelectFilter::make('assigned_user_id')
                    ->relationship('assignedUser', 'name')
                    ->label('Filter by User'),
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
