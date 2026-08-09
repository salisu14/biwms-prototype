<?php

declare(strict_types=1);

namespace App\Filament\Resources\Manufacturing\ProductionSchedules\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductionSchedulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('schedule_no')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('scheduling_mode')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('horizon_start_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('horizon_end_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('summary.operations_scheduled')
                    ->label('Ops')
                    ->alignEnd(),
                TextColumn::make('summary.exceptions')
                    ->label('Exceptions')
                    ->alignEnd(),
                TextColumn::make('summary.bottlenecks')
                    ->label('Bottlenecks')
                    ->alignEnd()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'generated' => 'Generated',
                        'reviewed' => 'Reviewed',
                        'approved' => 'Approved',
                        'superseded' => 'Superseded',
                        'cancelled' => 'Cancelled',
                    ]),
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
