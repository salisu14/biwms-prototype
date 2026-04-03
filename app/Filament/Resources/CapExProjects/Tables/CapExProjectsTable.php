<?php

namespace App\Filament\Resources\CapExProjects\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CapExProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('project_number')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->limit(40)
                    ->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'PENDING_APPROVAL' => 'warning',
                        'APPROVED' => 'success',
                        'IN_PROGRESS' => 'info',
                        'ON_HOLD' => 'gray',
                        'COMPLETED' => 'primary',
                        'CANCELLED' => 'danger',
                    }),

                TextColumn::make('budget_amount')
                    ->money()
                    ->sortable(),

                TextColumn::make('actual_amount')
                    ->money()
                    ->sortable()
                    ->color(fn ($record) => $record->actual_amount > $record->budget_amount ? 'danger' : 'success'),

                // Custom logic for completion or budget usage
                TextColumn::make('usage')
                    ->label('Budget Usage')
                    ->state(fn ($record) => $record->budget_amount > 0 ? ($record->actual_amount / $record->budget_amount) * 100 : 0)
                    ->formatStateUsing(fn ($state) => number_format($state, 0) . '%')
                    ->color(fn ($state) => $state > 100 ? 'danger' : 'gray'),

                TextColumn::make('projectManager.name')
                    ->label('Manager')
                    ->toggleable(),

                TextColumn::make('planned_end_date')
                    ->date()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options([
                        'PENDING_APPROVAL' => 'Pending Approval',
                        'APPROVED' => 'Approved',
                        'IN_PROGRESS' => 'In Progress',
                        'COMPLETED' => 'Completed',
                    ]),
                SelectFilter::make('project_manager_id')
                    ->relationship('projectManager', 'name')
                    ->label('Project Manager'),
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
