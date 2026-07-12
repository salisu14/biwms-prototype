<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterRoles\Tables;

use App\Models\WorkforceRosterRole;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class WorkforceRosterRolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name', 'asc')
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->sortable()
                    ->searchable()
                    ->weight('font-bold')
                    ->width('140px')
                    ->copyable(),

                TextColumn::make('name')
                    ->label('Role Name')
                    ->sortable()
                    ->searchable()
                    ->weight('font-medium'),

                TextColumn::make('business.name')
                    ->label('Business')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->width('140px'),

                TextColumn::make('department.name')
                    ->label('Department')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->placeholder('—'),

                TextColumn::make('workCenter.name')
                    ->label('Work Center')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->placeholder('—'),

                IconColumn::make('is_critical')
                    ->label('Critical')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('danger')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->tooltip(fn (WorkforceRosterRole $record): string => $record->is_critical ? 'Critical — must always be staffed' : 'Non-critical'
                    ),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->alignCenter(),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->tooltip(fn (WorkforceRosterRole $record): ?string => $record->description)
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('business_id')
                    ->label('Business')
                    ->relationship('business', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                SelectFilter::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                TernaryFilter::make('is_critical')
                    ->label('Critical Roles')
                    ->placeholder('All roles')
                    ->trueLabel('Critical only')
                    ->falseLabel('Non-critical only'),

                TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All roles')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No roster roles defined')
            ->emptyStateDescription('Create roster roles to categorize staffing requirements and shift assignments.')
            ->emptyStateIcon('heroicon-o-user-circle');
    }
}
