<?php

namespace App\Filament\Resources\EmployeePromotionHistories\Tables;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeePromotionHistoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('effective_date')
                    ->label('Effective Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('employee.employee_number')
                    ->label('Employee No.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee.first_name')
                    ->label('Employee')
                    ->formatStateUsing(fn ($state, $record) => trim(($record->employee?->first_name ?? '').' '.($record->employee?->last_name ?? '')))
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('old_job_title')
                    ->label('Old Title')
                    ->toggleable(),
                TextColumn::make('new_job_title')
                    ->label('New Title')
                    ->sortable(),
                TextColumn::make('oldDepartment.name')
                    ->label('Old Department')
                    ->toggleable(),
                TextColumn::make('newDepartment.name')
                    ->label('New Department')
                    ->toggleable(),
                TextColumn::make('old_base_salary')
                    ->label('Old Salary')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('new_base_salary')
                    ->label('New Salary')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('reason_code')
                    ->badge()
                    ->searchable(),
                TextColumn::make('promotedByUser.name')
                    ->label('Promoted By')
                    ->toggleable(),
                TextColumn::make('audit_note')
                    ->label('Audit Note')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->audit_note)
                    ->wrap(),
            ])
            ->filters([
                Filter::make('effective_date')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('effective_date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('effective_date', '<=', $date));
                    }),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
