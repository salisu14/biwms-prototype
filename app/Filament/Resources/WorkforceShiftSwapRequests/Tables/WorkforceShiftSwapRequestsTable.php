<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceShiftSwapRequests\Tables;

use App\Models\WorkforceShiftSwapRequest;
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
use Illuminate\Database\Query\Builder;

class WorkforceShiftSwapRequestsTable
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

                TextColumn::make('requester.full_name')
                    ->label('Requester')
                    ->sortable()
                    ->searchable()
                    ->weight('font-medium'),

                TextColumn::make('requesterAssignment.shift.name')
                    ->label('Requester Shift')
                    ->placeholder('—')
                    ->badge()
                    ->color('info'),

                TextColumn::make('target.full_name')
                    ->label('Target Employee')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('targetAssignment.shift.name')
                    ->label('Target Shift')
                    ->placeholder('—')
                    ->badge()
                    ->color('warning'),

                TextColumn::make('swap_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'direct' => 'Direct',
                        'coverage' => 'Coverage',
                        'partial' => 'Partial',
                        default => $state,
                    })
                    ->colors([
                        'success' => 'direct',
                        'warning' => 'coverage',
                        'info' => 'partial',
                    ])
                    ->icons([
                        'heroicon-o-arrows-right-left' => 'direct',
                        'heroicon-o-shield-check' => 'coverage',
                        'heroicon-o-clock' => 'partial',
                    ])
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => WorkforceShiftSwapRequest::STATUS_DRAFT,
                        'primary' => WorkforceShiftSwapRequest::STATUS_SUBMITTED,
                        'warning' => WorkforceShiftSwapRequest::STATUS_AWAITING_EMPLOYEE_ACCEPTANCE,
                        'info' => WorkforceShiftSwapRequest::STATUS_ACCEPTED_BY_EMPLOYEE,
                        'danger' => WorkforceShiftSwapRequest::STATUS_MANAGER_REVIEW,
                        'success' => WorkforceShiftSwapRequest::STATUS_APPROVED,
                        'danger' => WorkforceShiftSwapRequest::STATUS_REJECTED,
                        'secondary' => WorkforceShiftSwapRequest::STATUS_CANCELLED,
                        'gray' => WorkforceShiftSwapRequest::STATUS_EXPIRED,
                    ])
                    ->icons([
                        'heroicon-o-pencil' => WorkforceShiftSwapRequest::STATUS_DRAFT,
                        'heroicon-o-paper-airplane' => WorkforceShiftSwapRequest::STATUS_SUBMITTED,
                        'heroicon-o-clock' => WorkforceShiftSwapRequest::STATUS_AWAITING_EMPLOYEE_ACCEPTANCE,
                        'heroicon-o-hand-thumb-up' => WorkforceShiftSwapRequest::STATUS_ACCEPTED_BY_EMPLOYEE,
                        'heroicon-o-eye' => WorkforceShiftSwapRequest::STATUS_MANAGER_REVIEW,
                        'heroicon-o-check-badge' => WorkforceShiftSwapRequest::STATUS_APPROVED,
                        'heroicon-o-x-circle' => WorkforceShiftSwapRequest::STATUS_REJECTED,
                        'heroicon-o-archive-box' => WorkforceShiftSwapRequest::STATUS_CANCELLED,
                        'heroicon-o-x-mark' => WorkforceShiftSwapRequest::STATUS_EXPIRED,
                    ])
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('M d, H:i')
                    ->sortable()
                    ->color(fn (WorkforceShiftSwapRequest $record): string => $record->expires_at && $record->expires_at->isPast() && $record->status !== WorkforceShiftSwapRequest::STATUS_EXPIRED
                        ? 'danger'
                        : 'gray'
                    )
                    ->icon(fn (WorkforceShiftSwapRequest $record): ?string => $record->expires_at && $record->expires_at->isPast() && $record->status !== WorkforceShiftSwapRequest::STATUS_EXPIRED
                        ? 'heroicon-o-exclamation-triangle'
                        : null
                    ),

                IconColumn::make('is_overdue')
                    ->label('Overdue')
                    ->boolean()
                    ->getStateUsing(fn (WorkforceShiftSwapRequest $record): bool => $record->expires_at
                        && $record->expires_at->isPast()
                        && in_array($record->status, [
                            WorkforceShiftSwapRequest::STATUS_DRAFT,
                            WorkforceShiftSwapRequest::STATUS_SUBMITTED,
                            WorkforceShiftSwapRequest::STATUS_AWAITING_EMPLOYEE_ACCEPTANCE,
                        ])
                    )
                    ->trueIcon('heroicon-o-exclamation-circle')
                    ->trueColor('danger')
                    ->falseIcon('heroicon-o-check-circle')
                    ->falseColor('success')
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        WorkforceShiftSwapRequest::STATUS_DRAFT => 'Draft',
                        WorkforceShiftSwapRequest::STATUS_SUBMITTED => 'Submitted',
                        WorkforceShiftSwapRequest::STATUS_AWAITING_EMPLOYEE_ACCEPTANCE => 'Awaiting Acceptance',
                        WorkforceShiftSwapRequest::STATUS_ACCEPTED_BY_EMPLOYEE => 'Accepted',
                        WorkforceShiftSwapRequest::STATUS_MANAGER_REVIEW => 'Manager Review',
                        WorkforceShiftSwapRequest::STATUS_APPROVED => 'Approved',
                        WorkforceShiftSwapRequest::STATUS_REJECTED => 'Rejected',
                        WorkforceShiftSwapRequest::STATUS_CANCELLED => 'Cancelled',
                        WorkforceShiftSwapRequest::STATUS_EXPIRED => 'Expired',
                    ])
                    ->multiple()
                    ->native(false),

                SelectFilter::make('swap_type')
                    ->options([
                        'direct' => 'Direct Swap',
                        'coverage' => 'Coverage',
                        'partial' => 'Partial',
                    ])
                    ->multiple()
                    ->native(false),

                TernaryFilter::make('is_expired')
                    ->label('Expired')
                    ->placeholder('All requests')
                    ->trueLabel('Expired')
                    ->falseLabel('Active')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('expires_at')->where('expires_at', '<', now()),
                        false: fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', now())
                        ),
                    ),
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
            ->emptyStateHeading('No shift swap requests')
            ->emptyStateDescription('Create a request to swap shifts between employees.')
            ->emptyStateIcon('heroicon-o-arrows-right-left');
    }
}
