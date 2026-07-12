<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceShiftReplacements\Tables;

use App\Models\WorkforceShiftReplacement;
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

class WorkforceShiftReplacementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->width('60px')
                    ->alignCenter(),

                TextColumn::make('originalEmployee.full_name')
                    ->label('Original Employee')
                    ->sortable()
                    ->searchable()
                    ->weight('font-medium'),

                TextColumn::make('originalAssignment.shift.name')
                    ->label('Original Shift')
                    ->placeholder('—')
                    ->badge()
                    ->color('info'),

                TextColumn::make('replacementEmployee.full_name')
                    ->label('Replacement')
                    ->sortable()
                    ->searchable()
                    ->weight('font-medium'),

                TextColumn::make('replacementAssignment.shift.name')
                    ->label('Replacement Shift')
                    ->placeholder('—')
                    ->badge()
                    ->color('warning'),

                TextColumn::make('replacement_type')
                    ->badge()
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->colors([
                        'primary' => 'temporary',
                        'success' => 'permanent',
                        'danger' => 'emergency',
                        'info' => 'voluntary',
                        'warning' => 'mandatory',
                    ])
                    ->icons([
                        'heroicon-o-clock' => 'temporary',
                        'heroicon-o-check-circle' => 'permanent',
                        'heroicon-o-exclamation-triangle' => 'emergency',
                        'heroicon-o-hand-raised' => 'voluntary',
                        'heroicon-o-shield-check' => 'mandatory',
                    ])
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => WorkforceShiftReplacement::STATUS_DRAFT,
                        'primary' => WorkforceShiftReplacement::STATUS_PROPOSED,
                        'info' => WorkforceShiftReplacement::STATUS_ACCEPTED,
                        'success' => WorkforceShiftReplacement::STATUS_APPROVED,
                        'success' => WorkforceShiftReplacement::STATUS_COMPLETED,
                        'danger' => WorkforceShiftReplacement::STATUS_REJECTED,
                        'secondary' => WorkforceShiftReplacement::STATUS_CANCELLED,
                    ])
                    ->icons([
                        'heroicon-o-pencil' => WorkforceShiftReplacement::STATUS_DRAFT,
                        'heroicon-o-paper-airplane' => WorkforceShiftReplacement::STATUS_PROPOSED,
                        'heroicon-o-hand-thumb-up' => WorkforceShiftReplacement::STATUS_ACCEPTED,
                        'heroicon-o-check-badge' => WorkforceShiftReplacement::STATUS_APPROVED,
                        'heroicon-o-flag' => WorkforceShiftReplacement::STATUS_COMPLETED,
                        'heroicon-o-x-circle' => WorkforceShiftReplacement::STATUS_REJECTED,
                        'heroicon-o-archive-box' => WorkforceShiftReplacement::STATUS_CANCELLED,
                    ])
                    ->sortable(),

                IconColumn::make('may_create_overtime')
                    ->label('OT')
                    ->boolean()
                    ->trueIcon('heroicon-o-clock')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->tooltip(fn (WorkforceShiftReplacement $record): string => $record->may_create_overtime ? 'May generate overtime' : 'No overtime'
                    ),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        WorkforceShiftReplacement::STATUS_DRAFT => 'Draft',
                        WorkforceShiftReplacement::STATUS_PROPOSED => 'Proposed',
                        WorkforceShiftReplacement::STATUS_ACCEPTED => 'Accepted',
                        WorkforceShiftReplacement::STATUS_APPROVED => 'Approved',
                        WorkforceShiftReplacement::STATUS_REJECTED => 'Rejected',
                        WorkforceShiftReplacement::STATUS_CANCELLED => 'Cancelled',
                        WorkforceShiftReplacement::STATUS_COMPLETED => 'Completed',
                    ])
                    ->multiple()
                    ->native(false),

                SelectFilter::make('replacement_type')
                    ->options([
                        'temporary' => 'Temporary',
                        'permanent' => 'Permanent',
                        'emergency' => 'Emergency',
                        'voluntary' => 'Voluntary',
                        'mandatory' => 'Mandatory',
                    ])
                    ->multiple()
                    ->native(false),

                TernaryFilter::make('may_create_overtime')
                    ->label('Overtime')
                    ->placeholder('All')
                    ->trueLabel('May create OT')
                    ->falseLabel('No OT'),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
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
            ->emptyStateHeading('No shift replacements')
            ->emptyStateDescription('Create a replacement to assign another employee to cover a shift.')
            ->emptyStateIcon('heroicon-o-user-plus');
    }
}
