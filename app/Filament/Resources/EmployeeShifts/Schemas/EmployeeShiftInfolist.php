<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeShifts\Schemas;

use App\Models\EmployeeShift;
use Carbon\Carbon;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeShiftInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([self::makeGrid()]);
    }

    private static function makeGrid(): Grid
    {
        return Grid::make(3)->schema([
            self::identityColumn(), self::timingColumn(), self::rulesColumn(),
        ]);
    }

    private static function identityColumn(): Group
    {
        return Group::make([
            Section::make('Shift Details')->icon('heroicon-o-document-text')->schema([
                Grid::make(2)->schema([
                    TextEntry::make('code')->label('Code')->weight('bold')->size('lg')->copyable(),
                    TextEntry::make('name')->label('Name')->weight('medium')->columnSpanFull(),
                    IconEntry::make('is_active')->label('Active')->boolean()
                        ->trueIcon('heroicon-o-check-circle')->trueColor('success')
                        ->falseIcon('heroicon-o-x-circle')->falseColor('gray'),
                    IconEntry::make('is_weekend')->label('Weekend Shift')->boolean()
                        ->trueIcon('heroicon-o-moon')->trueColor('purple'),
                ]),
            ]),
        ]);
    }

    private static function timingColumn(): Group
    {
        return Group::make([
            Section::make('Working Hours')->icon('heroicon-o-clock')->schema([
                Grid::make(1)->schema([
                    TextEntry::make('time_display')->label('Scheduled Hours')
                        ->getStateUsing(fn (EmployeeShift $s): string => $s->start_time && $s->end_time
                            ? '**'.substr($s->start_time, 0, 5).' → '.substr($s->end_time, 0, 5).'**'
                            : 'Not set'
                        )->size('xl')->weight('bold')->markdown()
                        ->color(fn (EmployeeShift $s): string => $s->crosses_midnight ? 'danger' : 'primary'),

                    TextEntry::make('gross_duration')->label('Gross Duration')
                        ->getStateUsing(function (EmployeeShift $s): string {
                            if (! $s->start_time || ! $s->end_time) {
                                return '—';
                            }
                            try {
                                $st = Carbon::createFromFormat('H:i:s', $s->start_time);
                                $et = Carbon::createFromFormat('H:i:s', $s->end_time);
                                if ($s->crosses_midnight || $et->lt($st)) {
                                    $et->addDay();
                                }
                                $m = $st->diffInMinutes($et);

                                return floor($m / 60).'h '.($m % 60).'m ('.$m.' min)';
                            } catch (\Exception $e) {
                                return '—';
                            }
                        }),

                    IconEntry::make('crosses_midnight')->label('Crosses Midnight')->boolean()
                        ->trueIcon('heroicon-o-moon')->trueColor('danger'),
                    //                        ->trueTooltip('Night shift ending after midnight'),
                ]),
            ]),

            Section::make('Deductions')->icon('heroicon-o-minus-circle')->schema([
                TextEntry::make('break_minutes')->label('Break Time')
                    ->formatStateUsing(fn ($s): string => $s ? ((int) $s >= 60 ? floor((int) $s / 60).'h '.((int) $s % 60).'m' : $s.' min') : 'None')
                    ->icon('heroicon-o-coffee-cup'),

                TextEntry::make('net_duration')->label('Net Working Time')
                    ->getStateUsing(function (EmployeeShift $s): string {
                        if (! $s->start_time || ! $s->end_time) {
                            return '—';
                        }
                        try {
                            $st = Carbon::createFromFormat('H:i:s', $s->start_time);
                            $et = Carbon::createFromFormat('H:i:s', $s->end_time);
                            if ($s->crosses_midnight || $et->lt($st)) {
                                $et->addDay();
                            }
                            $net = max(0, $st->diffInMinutes($et) - (int) ($s->break_minutes ?? 0));

                            return round($net / 60, 2).' hours';
                        } catch (\Exception $e) {
                            return '—';
                        }
                    })
                    ->size('lg')->weight('bold')->color('success'),
            ]),
        ]);
    }

    private static function rulesColumn(): Group
    {
        return Group::make([
            Section::make('Flexibility Rules')->icon('heroicon-o-adjustments-horizontal')->schema([
                Grid::make(1)->schema([
                    TextEntry::make('grace_minutes')->label('Late Arrival Grace')
                        ->formatStateUsing(fn ($s): string => $s ? "{$s} minutes" : 'None')
                        ->icon('heroicon-o-clock'),
                    TextEntry::make('early_departure_grace_minutes')->label('Early Departure Grace')
                        ->formatStateUsing(fn ($s): string => $s ? "{$s} minutes" : 'None')
                        ->icon('heroicon-o-arrow-trending-down'),
                    TextEntry::make('overtime_threshold_minutes')->label('Overtime Threshold')
                        ->formatStateUsing(fn ($s): string => $s ? "+{$s} min triggers OT" : 'No threshold')
                        ->icon('heroicon-o-plus-circle')
                        ->color('warning'),
                ]),
            ]),

            Section::make('Usage Statistics')->icon('heroicon-o-chart-bar')->collapsed()
                ->visible(fn (EmployeeShift $s): bool => $s !== null && $s->exists)
                ->schema([
                    TextEntry::make('assignments_count')->label('Active Assignments')
                        ->getStateUsing(fn (EmployeeShift $s): int => $s->scheduleAssignments()->count())
                        ->formatStateUsing(fn (int $s): string => number_format($s).' employee(s)')
                        ->size('lg')->weight('bold'),
                ]),

            Section::make('System')->icon('heroicon-o-information-circle')->collapsed()->schema([
                Grid::make(2)->schema([
                    TextEntry::make('id')->label('ID')->copyable()->size('sm')->color('gray'),
                    TextEntry::make('updated_at')->label('Updated')->dateTime('M d, Y g:i A')->since()->size('sm'),
                ]),
            ]),
        ]);
    }
}
