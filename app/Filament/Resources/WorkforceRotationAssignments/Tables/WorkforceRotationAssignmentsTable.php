<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRotationAssignments\Tables;

use App\Models\Employee;
use App\Models\WorkforceRotationAssignment;
use App\Models\WorkforceRotationTemplate;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class WorkforceRotationAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('effective_from', 'desc')
            ->columns(self::getColumns())
            ->filters(self::getFilters())
            ->actions(self::getActions())
            ->bulkActions(self::getBulkActions())
            ->emptyStateIcon('heroicon-o-arrow-path')
            ->emptyStateDescription('No rotation assignments found.');
    }

    private static function getColumns(): array
    {
        return [
            TextColumn::make('id')
                ->label('#')
//                ->toggleable(isToggledByDefault: false)
                ->sortable(),

            TextColumn::make('employee.full_name')
                ->label('Employee')
                ->searchable(['first_name', 'last_name', 'employee_number'])
                ->sortable()
                ->description(fn (WorkforceRotationAssignment $r): string => $r->employee?->employee_number ?? ''
                )
                ->weight('medium'),

            TextColumn::make('template.name')
                ->label('Rotation Template')
                ->searchable()
                ->sortable()
                ->badge()
                ->color('primary'),

            TextColumn::make('effective_from')
                ->label('Effective From')
                ->date('M d, Y')
                ->sortable(),

            TextColumn::make('effective_to')
                ->label('Effective To')
                ->date('M d, Y')
                ->sortable()
                ->placeholder('∞ Indefinite')
                ->color('gray'),

            IconColumn::make('is_primary')
                ->label('Primary')
                ->boolean()
                ->trueIcon('heroicon-o-star')
                ->trueColor('warning')
                ->falseIcon('heroicon-o-minus')
                ->falseColor('gray'),

            IconColumn::make('is_active')
                ->label('Active')
                ->boolean()
                ->trueIcon('heroicon-o-check-circle')
                ->trueColor('success')
                ->falseIcon('heroicon-o-x-circle')
                ->falseColor('gray'),

            TextColumn::make('starting_sequence_day')
                ->label('Seq. Day')
                ->sortable()
                ->alignment('center')
                ->badge()
                ->color('info'),

            TextColumn::make('workCenter.name')
                ->label('Work Center')
                ->toggleable()
                ->placeholder('—'),

            TextColumn::make('attendanceLocation.name')
                ->label('Location')
                ->toggleable()
                ->placeholder('—'),
        ];
    }

    private static function getFilters(): array
    {
        return [
            // ✅ FIXED: Simple select filter without relationship issues
            SelectFilter::make('is_active')
                ->label('Status')
                ->options([
                    '1' => 'Active',
                    '0' => 'Inactive',
                ]),

            SelectFilter::make('is_primary')
                ->label('Type')
                ->options([
                    '1' => 'Primary Only',
                    '0' => 'Secondary Only',
                ]),

            // ✅ FIXED: Use query() instead of relationship() to avoid null issues
            SelectFilter::make('workforce_rotation_template_id')
                ->label('Template')
                ->query(function ($query, ?string $search = null) {
                    if (! $search) {
                        return $query;
                    }

                    return $query->whereHas('template', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
                })
                ->options(function (): array {
                    // ✅ Safe query with fallback
                    try {
                        return WorkforceRotationTemplate::pluck('name', 'id')->toArray();
                    } catch (\Throwable $e) {
                        return [];
                    }
                })
                ->searchable()
                ->preload(),

            // ✅ FIXED: Same pattern for employee
            SelectFilter::make('employee_id')
                ->label('Employee')
                ->query(function ($query, ?string $search = null) {
                    if (! $search) {
                        return $query;
                    }

                    return $query->whereHas('employee', function ($q) use ($search) {
                        $q->where('full_name', 'like', "%{$search}%")
                            ->orWhere('employee_number', 'like', "%{$search}%");
                    });
                })
                ->options(function (): array {
                    try {
                        return Employee::where('is_active', true)
                            ->pluck('full_name', 'id')
                            ->toArray();
                    } catch (\Throwable $e) {
                        return [];
                    }
                })
                ->searchable()
                ->preload(),

            // ✅ FIXED: Proper null handling in date range filter
            Filter::make('effective_range')
                ->label('Effective Date Range')
                ->form([
                    DatePicker::make('date_from')
                        ->label('From'),
                    DatePicker::make('date_to')
                        ->label('To'),
                ])
                ->query(function ($query, array $data): ?object {
                    // ✅ Guard against null query
                    if (! $query instanceof Builder) {
                        return $query;
                    }

                    $from = $data['date_from'] ?? null;
                    $to = $data['date_to'] ?? null;

                    return $query
                        ->when($from, fn ($q) => $q->whereDate('effective_from', '>=', $from))
                        ->when($to, fn ($q) => $q->where(function ($q2) use ($to) {
                            $q2->whereNull('effective_to')
                                ->orWhereDate('effective_to', '<=', $to);
                        }));
                }),
        ];
    }

    private static function getActions(): array
    {
        return [
            ViewAction::make(),

            EditAction::make(),

            Action::make('toggle_active')
                ->label(fn (WorkforceRotationAssignment $r): string => $r->is_active ? 'Deactivate' : 'Activate'
                )
                ->icon(fn (WorkforceRotationAssignment $r): string => $r->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle'
                )
                ->requiresConfirmation()
                ->color(fn (WorkforceRotationAssignment $r): string => $r->is_active ? 'warning' : 'success'
                )
                ->action(fn (WorkforceRotationAssignment $r) => $r->update(['is_active' => ! $r->is_active])
                ),
        ];
    }

    private static function getBulkActions(): array
    {
        return [
            BulkActionGroup::make([
                DeleteBulkAction::make(),

                Action::make('bulk_deactivate')
                    ->label('Deactivate Selected')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->color('warning')
                    ->action(fn (Collection $records) => $records->each(fn (WorkforceRotationAssignment $r) => $r->update(['is_active' => false])
                    )
                    ),
            ]),
        ];
    }
}
