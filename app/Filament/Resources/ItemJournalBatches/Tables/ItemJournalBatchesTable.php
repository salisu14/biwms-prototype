<?php

namespace App\Filament\Resources\ItemJournalBatches\Tables;

use App\Enums\JournalLineType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ItemJournalBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('template.name')
                    ->label('Template')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('name')
                    ->label('Batch')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('assignedUser.name')
                    ->label('Assigned To')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Unassigned'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'gray',
                        'released' => 'info',
                        'posted' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('lines_count')
                    ->label('Entries')
                    ->counts('lines')
                    ->badge()
                    ->color('info'),

                TextColumn::make('location.code')
                    ->label('Loc.')
                    ->toggleable(),

                IconColumn::make('copy_item_dimensions')
                    ->label('Dims')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Modified')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('template_id')
                    ->relationship('template', 'name'),

                SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'released' => 'Released',
                        'posted' => 'Posted',
                        'cancelled' => 'Cancelled',
                    ]),

                SelectFilter::make('default_entry_type')
                    ->options(JournalLineType::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])->defaultSort('name', 'asc');
    }
}
