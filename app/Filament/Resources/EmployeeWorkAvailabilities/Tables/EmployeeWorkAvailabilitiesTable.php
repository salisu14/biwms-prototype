<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeWorkAvailabilities\Tables;

use App\Models\EmployeeWorkAvailability;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeeWorkAvailabilitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date_from', 'desc')
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->weight('font-medium'),

                TextColumn::make('availability_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::getAvailabilityTypeOptions()[$state] ?? $state)
                    ->colors([
                        'success' => EmployeeWorkAvailability::TYPE_AVAILABLE,
                        'danger' => EmployeeWorkAvailability::TYPE_UNAVAILABLE,
                        'info' => EmployeeWorkAvailability::TYPE_PREFERRED_SHIFT,
                        'warning' => EmployeeWorkAvailability::TYPE_RESTRICTED_SHIFT,
                        'primary' => EmployeeWorkAvailability::TYPE_OFFICIAL_DUTY,
                        'gray' => EmployeeWorkAvailability::TYPE_OTHER,
                    ])
                    ->icon(fn (string $state): string => match ($state) {
                        EmployeeWorkAvailability::TYPE_AVAILABLE => 'heroicon-o-check-circle',
                        EmployeeWorkAvailability::TYPE_UNAVAILABLE => 'heroicon-o-x-circle',
                        EmployeeWorkAvailability::TYPE_TRAINING => 'heroicon-o-academic-cap',
                        EmployeeWorkAvailability::TYPE_SUSPENSION => 'heroicon-o-no-symbol',
                        default => 'heroicon-o-clock',
                    })
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('date_range')
                    ->label('Period')
                    ->getStateUsing(fn (EmployeeWorkAvailability $record): string => $record->date_from->equalTo($record->date_to)
                        ? $record->date_from->format('M d, Y')
                        : "{$record->date_from->format('M d')} – {$record->date_to->format('M d, Y')}"
                    )
                    ->sortable(['date_from', 'date_to'])
                    ->searchable(['date_from', 'date_to'])
                    ->description(fn (EmployeeWorkAvailability $record): string => $record->date_from->diffInDays($record->date_to) === 0
                        ? '1 day'
                        : ($record->date_from->diffInDays($record->date_to) + 1).' days'
                    ),

                TextColumn::make('status')
                    ->badge(fn (EmployeeWorkAvailability $record): string => ucfirst($record->status))
                    ->colors([
                        'gray' => EmployeeWorkAvailability::STATUS_DRAFT,
                        'warning' => EmployeeWorkAvailability::STATUS_SUBMITTED,
                        'success' => EmployeeWorkAvailability::STATUS_APPROVED,
                        'danger' => EmployeeWorkAvailability::STATUS_REJECTED,
                        'secondary' => EmployeeWorkAvailability::STATUS_CANCELLED,
                    ])
                    ->icons([
                        'heroicon-o-pencil' => EmployeeWorkAvailability::STATUS_DRAFT,
                        'heroicon-o-paper-airplane' => EmployeeWorkAvailability::STATUS_SUBMITTED,
                        'heroicon-o-check-badge' => EmployeeWorkAvailability::STATUS_APPROVED,
                        'heroicon-o-x-mark' => EmployeeWorkAvailability::STATUS_REJECTED,
                        'heroicon-o-archive-box' => EmployeeWorkAvailability::STATUS_CANCELLED,
                    ])
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_confidential')
                    ->label('Confidential')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('availability_type')
                    ->options(self::getAvailabilityTypeOptions())
                    ->multiple()
                    ->native(false),

                SelectFilter::make('status')
                    ->options(self::getStatusOptions())
                    ->multiple()
                    ->native(false),

                TernaryFilter::make('is_confidential')
                    ->label('Confidential only')
                    ->placeholder('All records')
                    ->trueLabel('Confidential')
                    ->falseLabel('Non-confidential'),

                Filter::make('date_range')
                    ->schema([
                        DatePicker::make('date_from')->label('From'),
                        DatePicker::make('date_to')->label('To'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['date_from'], fn (Builder $query, $date) => $query->whereDate('date_from', '>=', $date)
                            )
                            ->when($data['date_to'], fn (Builder $query, $date) => $query->whereDate('date_to', '<=', $date)
                            );
                    }),
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
            ->emptyStateHeading('No availability records found')
            ->emptyStateDescription('Create a new record to track employee availability.')
            ->emptyStateIcon('heroicon-o-calendar');
    }

    private static function getAvailabilityTypeOptions(): array
    {
        return [
            EmployeeWorkAvailability::TYPE_AVAILABLE => 'Available',
            EmployeeWorkAvailability::TYPE_UNAVAILABLE => 'Unavailable',
            EmployeeWorkAvailability::TYPE_PREFERRED_SHIFT => 'Preferred Shift',
            EmployeeWorkAvailability::TYPE_RESTRICTED_SHIFT => 'Restricted Shift',
            EmployeeWorkAvailability::TYPE_OFFICIAL_DUTY => 'Official Duty',
            EmployeeWorkAvailability::TYPE_TRAINING => 'Training',
            EmployeeWorkAvailability::TYPE_TEMPORARY_ASSIGNMENT => 'Temporary Assignment',
            EmployeeWorkAvailability::TYPE_SUSPENSION => 'Suspension',
            EmployeeWorkAvailability::TYPE_OTHER => 'Other',
        ];
    }

    private static function getStatusOptions(): array
    {
        return [
            EmployeeWorkAvailability::STATUS_DRAFT => 'Draft',
            EmployeeWorkAvailability::STATUS_SUBMITTED => 'Submitted',
            EmployeeWorkAvailability::STATUS_APPROVED => 'Approved',
            EmployeeWorkAvailability::STATUS_REJECTED => 'Rejected',
            EmployeeWorkAvailability::STATUS_CANCELLED => 'Cancelled',
        ];
    }
}
