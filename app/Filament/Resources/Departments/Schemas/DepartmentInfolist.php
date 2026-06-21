<?php

namespace App\Filament\Resources\Departments\Schemas;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\Department;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DepartmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Replaced Split::make with Grid::make(2)
                Grid::make(2)
                    ->schema([
                        // Left Column
                        Group::make([
                            Section::make('General Information')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextEntry::make('department_code')->label('Code')->weight('bold'),
                                        TextEntry::make('name')->weight('bold'),
                                        TextEntry::make('type')->badge(),
                                        TextEntry::make('status')->badge(),
                                        TextEntry::make('parentDepartment.name')->label('Parent')->placeholder('Root Level'),
                                        TextEntry::make('full_path')->label('Hierarchy Path'),
                                    ]),
                                ]),
                            Section::make('Contact & Location')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextEntry::make('email')->icon('heroicon-m-envelope'),
                                        TextEntry::make('phone')->icon('heroicon-m-phone'),
                                        TextEntry::make('room_location'),
                                        TextEntry::make('location_code'),
                                    ]),
                                ]),
                            Section::make('Notes')
                                ->schema([
                                    TextEntry::make('notes')->markdown()->placeholder('No notes available.'),
                                ]),
                        ]), // Removed ->grow()

                        // Right Column
                        Group::make([
                            Section::make('Financial Summary')
                                ->schema([
                                    TextEntry::make('annual_budget')->money(),
                                    TextEntry::make('budget_utilized')->money()
                                        ->color(fn ($record) => $record->budget_utilization_percent > 90 ? 'danger' : 'success'),
                                    TextEntry::make('budget_utilization_percent')
                                        ->label('Utilization')
                                        ->suffix('%'),
                                    Grid::make(2)->schema([
                                        IconEntry::make('is_cost_center')->label('CC')->boolean(),
                                        IconEntry::make('is_profit_center')->label('PC')->boolean(),
                                    ]),
                                ]),
                            Section::make('People')
                                ->schema([
                                    TextEntry::make('manager_link')
                                        ->label('Manager')
                                        ->state(function (Department $record): string {
                                            $employee = $record->manager;

                                            if (! $employee) {
                                                return 'Unassigned';
                                            }

                                            return "{$employee->employee_number} - {$employee->first_name} {$employee->last_name}";
                                        })
                                        ->url(fn (Department $record): ?string => $record->manager
                                            ? EmployeeResource::getUrl('view', ['record' => $record->manager])
                                            : null),
                                    TextEntry::make('approver_link')
                                        ->label('Default Approver')
                                        ->state(function (Department $record): string {
                                            $employee = $record->approver?->employee;

                                            if (! $employee) {
                                                return $record->approver?->name ?? 'Unassigned';
                                            }

                                            return "{$employee->employee_number} - {$employee->first_name} {$employee->last_name}";
                                        })
                                        ->url(fn (Department $record): ?string => $record->approver?->employee
                                            ? EmployeeResource::getUrl('view', ['record' => $record->approver->employee])
                                            : null),
                                ]),
                            Section::make('Administration')
                                ->schema([
                                    TextEntry::make('starting_date')->date(),
                                    TextEntry::make('blocked_at')->dateTime()->color('danger'),
                                ]),
                        ]), // Removed ->columnSpan(1)
                    ]),
            ]);
    }
}
