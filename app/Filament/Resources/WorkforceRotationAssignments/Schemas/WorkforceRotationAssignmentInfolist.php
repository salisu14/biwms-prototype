<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRotationAssignments\Schemas;

use App\Models\WorkforceRotationAssignment;
use Carbon\Carbon;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkforceRotationAssignmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([self::makeGrid()]);
    }

    private static function makeGrid(): Grid
    {
        return Grid::make(3)->schema([
            self::identityColumn(), self::configurationColumn(), self::systemColumn(),
        ]);
    }

    private static function identityColumn(): Group
    {
        return Group::make([
            Section::make('Assignment Overview')->icon('heroicon-o-arrow-path')->schema([
                Grid::make(2)->schema([
                    TextEntry::make('employee.full_name')->label('Employee')->weight('bold')->size('lg')
                        ->url(fn (WorkforceRotationAssignment $r): ?string => route('filament.admin.resources.employees.view', ['record' => $r->employee_id])
                            //                        )
                            //                        ->description(fn (WorkforceRotationAssignment $r): string =>
                            //                            $r->employee?->employee_number ?? ''
                        )->columnSpanFull(),

                    TextEntry::make('template.name')->label('Rotation Template')->badge()->color('primary')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->url(fn (WorkforceRotationAssignment $r): ?string => $r->template ? route('filament.admin.resources.workforce-rotation-templates.view', ['record' => $r->workforce_rotation_template_id]) : null
                        ),

                    IconEntry::make('is_primary')->label('Primary Rotation')->boolean()
                        ->trueIcon('heroicon-o-star')->trueColor('warning')
                        ->falseIcon('heroicon-o-minus-circle')->falseColor('gray'),

                    IconEntry::make('is_active')->label('Active Status')->boolean()
                        ->trueIcon('heroicon-o-check-circle')->trueColor('success')
                        ->falseIcon('heroicon-o-x-circle')->falseColor('gray'),
                ]),
            ]),

            Section::make('Status Summary')->icon('heroicon-o-shield-check')->schema([
                TextEntry::make('current_state')->label('State')->state(function (WorkforceRotationAssignment $r): string {
                    if ($r->is_active && $r->is_primary) {
                        return '🟢 Active Primary Rotation';
                    }
                    if ($r->is_active && ! $r->is_primary) {
                        return '🟡 Active Secondary Rotation';
                    }

                    return '⚪ Inactive';
                })->badge()->color(fn (WorkforceRotationAssignment $r): string => $r->is_active ? 'success' : 'gray'),
            ]),
        ]);
    }

    private static function configurationColumn(): Group
    {
        return Group::make([
            Section::make('Effective Dates')->icon('heroicon-o-calendar')->schema([
                Grid::make(1)->schema([
                    TextEntry::make('effective_from')->label('Effective From')->date('F j, Y')
                        ->icon('heroicon-o-arrow-start-on-rectangle')
                        ->formatStateUsing(fn (string $s): string => Carbon::parse($s)->format('F j, Y (l)')),

                    TextEntry::make('effective_to')->label('Effective To')->date('F j, Y')
                        ->icon('heroicon-o-arrow-end-on-rectangle')
                        ->placeholder('∞ Indefinite (no end date)')
                        ->color('gray'),
                ]),
            ]),

            Section::make('Cycle Settings')->icon('heroicon-o-refresh')->schema([
                Grid::make(2)->schema([
                    TextEntry::make('cycle_start_date')->label('Cycle Start')->date('F j, Y')
                        ->icon('heroicon-o-play-circle'),

                    TextEntry::make('starting_sequence_day')->label('Sequence Day')
                        ->formatStateUsing(fn ($s): string => "Day {$s} of cycle")
                        ->badge()->color('info')->icon('heroicon-o-hashtag'),
                ]),
            ]),

            Section::make('Location Scope')->icon('heroicon-o-map-pin')->schema([
                Grid::make(1)->schema([
                    TextEntry::make('workCenter.name')->label('Work Center')
                        ->icon('heroicon-o-cog-6-tooth')->placeholder('Not restricted'),
                    TextEntry::make('attendanceLocation.name')->label('Attendance Location')
                        ->icon('heroicon-o-map-pin')->placeholder('Use template default'),
                ]),
            ]),
        ]);
    }

    private static function systemColumn(): Group
    {
        return Group::make([
            Section::make('System Information')->icon('heroicon-o-information-circle')->collapsed()->schema([
                Grid::make(2)->schema([
                    TextEntry::make('id')->label('Record ID')->copyable()->icon('heroicon-o-fingerprint')
                        ->size('sm')->color('gray'),
                    TextEntry::make('created_at')->label('Created')->dateTime('M d, Y g:i A')
                        ->since()->icon('heroicon-o-plus-circle')->size('sm'),
                    TextEntry::make('updated_at')->label('Updated')->dateTime('M d, Y g:i A')
                        ->since()->icon('heroicon-o-arrow-path')->size('sm'),
                    TextEntry::make('assignedBy.name')->label('Assigned By')->icon('heroicon-o-user')
                        ->size('sm')->placeholder('System'),
                ]),
            ]),
        ]);
    }
}
