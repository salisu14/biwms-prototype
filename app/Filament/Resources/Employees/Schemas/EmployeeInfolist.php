<?php

declare(strict_types=1);

namespace App\Filament\Resources\Employees\Schemas;

use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Employee;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)->schema([
                    Section::make('Identity')
                        ->schema([
                            Grid::make(2)->schema([
                                TextEntry::make('employee_number')->label('Employee No.')->weight('bold'),
                                TextEntry::make('full_name')->label('Full Name')->weight('bold'),
                                TextEntry::make('job_title')->label('Job Title'),
                                TextEntry::make('assignment_type')
                                    ->label('Assignment')
                                    ->badge()
                                    ->formatStateUsing(function ($state): string {
                                        return is_object($state) && method_exists($state, 'getLabel')
                                            ? $state->getLabel() ?? '—'
                                            : (string) ($state ?? '—');
                                    }),
                                TextEntry::make('is_active')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive'),
                            ]),
                        ]),

                    Section::make('Organization')
                        ->schema([
                            // Wrapped in a 3-column grid to align horizontally and reduce height
                            Grid::make(3)->schema([
                                TextEntry::make('department_link')
                                    ->label('Department')
                                    ->state(function (Employee $record): string {
                                        $department = $record->department;

                                        if (! $department) {
                                            return 'Unassigned';
                                        }

                                        return "{$department->department_code} - {$department->name}";
                                    })
                                    ->url(fn (Employee $record): ?string => $record->department
                                        ? DepartmentResource::getUrl('view', ['record' => $record->department])
                                        : null),
                                TextEntry::make('business_code')
                                    ->label('Business')
                                    ->placeholder('—'),
                                TextEntry::make('factory_code')
                                    ->label('Factory')
                                    ->placeholder('—'),
                                TextEntry::make('employeePostingGroup.code')
                                    ->label('Employee Posting Group')
                                    ->placeholder('—'),
                                TextEntry::make('payrollPostingGroup.code')
                                    ->label('Payroll Posting Group')
                                    ->placeholder('—'),
                            ]),
                        ]),

                    Section::make('Contact & Access')
                        ->schema([
                            Grid::make(2)->schema([
                                TextEntry::make('email')->icon('heroicon-m-envelope'),
                                TextEntry::make('phone')->icon('heroicon-m-phone'),
                                TextEntry::make('user_link')
                                    ->label('User Account')
                                    ->state(function (Employee $record): string {
                                        $user = $record->user;

                                        if (! $user) {
                                            return 'Unassigned';
                                        }

                                        return "{$user->name} - {$user->email}";
                                    })
                                    ->url(fn (Employee $record): ?string => $record->user
                                        ? UserResource::getUrl('view', ['record' => $record->user])
                                        : null),
                            ]),
                        ]),

                    Section::make('Record Details')
                        ->schema([
                            // Wrapped in a 2-column grid to sit side-by-side
                            Grid::make(2)->schema([
                                TextEntry::make('created_at')->dateTime()->label('Created'),
                                TextEntry::make('updated_at')->dateTime()->label('Updated'),
                            ]),
                        ]),
                ]),
            ]);
    }
}
