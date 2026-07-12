<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceStaffingRequirements\Schemas;

use App\Models\WorkforceStaffingRequirement;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

class WorkforceStaffingRequirementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Organization')
                    ->icon('heroicon-o-building-office-2')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('business.name')
                            ->label('Business')
                            ->icon('heroicon-o-building-office')
                            ->weight('font-bold'),

                        TextEntry::make('department.name')
                            ->label('Department')
                            ->icon('heroicon-o-users')
                            ->placeholder('Not assigned'),

                        TextEntry::make('workCenter.name')
                            ->label('Work Center')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->placeholder('Not assigned'),
                    ]),

                Section::make('Location & Shift')
                    ->icon('heroicon-o-map-pin')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('attendanceLocation.name')
                            ->label('Attendance Location')
                            ->icon('heroicon-o-map-pin')
                            ->placeholder('Not assigned'),

                        TextEntry::make('employeeShift.name')
                            ->label('Shift')
                            ->icon('heroicon-o-clock')
                            ->badge()
                            ->color('primary')
                            ->placeholder('Not assigned'),

                        TextEntry::make('weekday')
                            ->label('Weekday')
                            ->formatStateUsing(fn (int $state): string => match ($state) {
                                1 => 'Monday',
                                2 => 'Tuesday',
                                3 => 'Wednesday',
                                4 => 'Thursday',
                                5 => 'Friday',
                                6 => 'Saturday',
                                7 => 'Sunday',
                                default => 'Unknown',
                            })
                            ->color(fn (int $state): string => match ($state) {
                                1, 2, 3, 4, 5 => 'primary',
                                6 => 'warning',
                                7 => 'danger',
                                default => 'gray',
                            })
                            ->icon(fn (int $state): string => match ($state) {
                                1, 2, 3, 4, 5 => 'heroicon-o-briefcase',
                                6, 7 => 'heroicon-o-sun',
                                default => 'heroicon-o-question-mark-circle',
                            }),
                    ]),

                Section::make('Role')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        TextEntry::make('rosterRole.name')
                            ->label('Roster Role')
                            ->icon('heroicon-o-identification')
                            ->weight('font-bold')
                            ->size(TextSize::Large),

                        TextEntry::make('rosterRole.description')
                            ->label('Role Description')
                            ->placeholder('No description provided')
                            ->prose(),
                    ]),

                Section::make('Staffing Levels')
                    ->icon('heroicon-o-users')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('minimum_required')
                            ->label('Minimum Required')
                            ->icon('heroicon-o-arrow-down-circle')
                            ->color('danger')
                            ->weight('font-bold')
                            ->size(TextSize::Large)
                            ->suffix(' staff'),

                        TextEntry::make('maximum_allowed')
                            ->label('Maximum Allowed')
                            ->icon('heroicon-o-arrow-up-circle')
                            ->color('success')
                            ->weight('font-bold')
                            ->size(TextSize::Large)
                            ->suffix(' staff'),

                        TextEntry::make('coverage_analysis')
                            ->label('Coverage Analysis')
                            ->state(function (WorkforceStaffingRequirement $record): string {
                                $gap = max(0, $record->target_required - $record->minimum_required);
                                $flex = max(0, $record->maximum_allowed - $record->target_required);

                                return "Gap: {$gap} | Flex: {$flex}";
                            })
                            ->icon('heroicon-o-calculator')
                            ->color('gray'),
                    ]),

                Section::make('Validity Period')
                    ->icon('heroicon-o-calendar')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('effective_from')
                            ->label('Effective From')
                            ->date('F j, Y')
                            ->icon('heroicon-o-calendar-days'),

                        TextEntry::make('effective_to')
                            ->label('Effective To')
                            ->date('F j, Y')
                            ->placeholder('No end date (ongoing)')
                            ->icon('heroicon-o-calendar-days'),

                        IconEntry::make('is_active')
                            ->label('Status')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),
                        //                            ->trueLabel('Active')
                        //                            ->falseLabel('Inactive'),
                    ]),

                Section::make('Audit Trail')
                    ->icon('heroicon-o-clock')
                    ->collapsible()
                    ->collapsed()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime('M d, Y H:i')
                            ->icon('heroicon-o-plus-circle'),

                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime('M d, Y H:i')
                            ->icon('heroicon-o-arrow-path'),
                    ]),
            ]);
    }
}
