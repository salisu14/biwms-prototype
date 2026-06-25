<?php

namespace App\Filament\Resources\Departments\Tables;

use App\Enums\DepartmentStatus;
use App\Enums\DepartmentType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class DepartmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('department_code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->department_path),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('budget_utilized')
                    ->label('Budget Utilization')
                    ->money('NGN')
                    ->description(fn ($record) => 'of '.number_format((float) $record->annual_budget, 2).' ('.$record->budget_utilization_percent.'%)')
                    ->sortable(),
                IconColumn::make('is_cost_center')
                    ->label('CC')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('manager_id')
                    ->label('Manager')
                    ->formatStateUsing(function ($state, $record): string {
                        $employee = $record->manager;

                        if (! $employee) {
                            return 'Not Assigned';
                        }

                        return "{$employee->employee_number} - {$employee->first_name} {$employee->last_name}";
                    })
                    ->placeholder('Not Assigned')
                    ->toggleable(),
                TextColumn::make('location_code')
                    ->label('Location')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(DepartmentStatus::class),
                SelectFilter::make('type')
                    ->options(DepartmentType::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Delete Selected'),
                    ForceDeleteBulkAction::make()
                        ->label('Force Delete Selected'),
                    RestoreBulkAction::make()
                        ->label('Restore Selected'),
                ]),
            ]);
    }
}
