<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterHistories\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

class WorkforceRosterHistoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Event Overview')
                    ->icon('heroicon-o-bolt')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('event_type')
                            ->label('Event Type')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state)))
                            ->color(fn (string $state): string => match ($state) {
                                'created', 'published', 'approved', 'resolved' => 'success',
                                'updated', 'shift_changed', 'location_changed', 'role_changed', 'overtime_flagged' => 'warning',
                                'deleted', 'cancelled', 'rejected', 'conflict_detected' => 'danger',
                                'replaced', 'swapped' => 'info',
                                default => 'gray',
                            })
                            ->icon(fn (string $state): string => match ($state) {
                                'created' => 'heroicon-o-plus-circle',
                                'updated' => 'heroicon-o-pencil',
                                'deleted' => 'heroicon-o-trash',
                                'published' => 'heroicon-o-paper-airplane',
                                'cancelled' => 'heroicon-o-x-circle',
                                'replaced' => 'heroicon-o-arrow-path',
                                'swapped' => 'heroicon-o-arrows-right-left',
                                'approved' => 'heroicon-o-check-badge',
                                'rejected' => 'heroicon-o-no-symbol',
                                'shift_changed' => 'heroicon-o-clock',
                                'location_changed' => 'heroicon-o-map-pin',
                                'role_changed' => 'heroicon-o-user-circle',
                                'overtime_flagged' => 'heroicon-o-bolt',
                                'conflict_detected' => 'heroicon-o-exclamation-triangle',
                                'resolved' => 'heroicon-o-check-circle',
                                default => 'heroicon-o-question-mark-circle',
                            }),

                        TextEntry::make('changed_at')
                            ->label('Changed At')
                            ->dateTime('F j, Y \a\t g:i A')
                            ->icon('heroicon-o-clock'),

                        TextEntry::make('changedBy.name')
                            ->label('Changed By')
                            ->icon('heroicon-o-user-circle')
                            ->placeholder('System / Automated'),
                    ]),

                Section::make('Roster Context')
                    ->icon('heroicon-o-calendar')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('workforceRosterPeriod.name')
                            ->label('Roster Period')
                            ->icon('heroicon-o-calendar-days')
                            ->weight('font-bold'),

                        TextEntry::make('workforceRosterAssignment.id')
                            ->label('Assignment ID')
                            ->icon('heroicon-o-hashtag')
                            ->formatStateUsing(fn (?int $state): string => $state ? "#{$state}" : '—'),

                        TextEntry::make('employee.full_name')
                            ->label('Affected Employee')
                            ->icon('heroicon-o-user')
                            ->weight('font-bold')
                            ->size(TextSize::Large),

                        TextEntry::make('workforceRosterAssignment.work_date')
                            ->label('Work Date')
                            ->date('l, F j, Y')
                            ->icon('heroicon-o-calendar'),
                    ]),

                Section::make('Reason')
                    ->icon('heroicon-o-document-text')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('reason')
                            ->label('Change Reason')
                            ->markdown()
                            ->prose()
                            ->placeholder('No reason provided')
                            ->columnSpanFull(),
                    ]),

                Section::make('Value Changes')
                    ->icon('heroicon-o-arrows-right-left')
                    ->columns(2)
                    ->schema([
                        KeyValueEntry::make('before_values')
                            ->label('Before')
                            ->placeholder('No previous values'),

                        KeyValueEntry::make('after_values')
                            ->label('After')
                            ->placeholder('No new values'),
                    ]),

                Section::make('Processing Flags')
                    ->icon('heroicon-o-flag')
                    ->columns(3)
                    ->schema([
                        IconEntry::make('employee_notified')
                            ->label('Employee Notified')
                            ->boolean()
                            ->trueIcon('heroicon-o-bell')
                            ->falseIcon('heroicon-o-bell-slash')
                            ->trueColor('success')
                            ->falseColor('gray'),
                        //                            ->trueLabel('Employee was notified of this change')
                        //                            ->falseLabel('Employee was not notified'),

                        IconEntry::make('attendance_recalculated')
                            ->label('Attendance Recalculated')
                            ->boolean()
                            ->trueIcon('heroicon-o-calculator')
                            ->falseIcon('heroicon-o-minus')
                            ->trueColor('info')
                            ->falseColor('gray'),
                        //                            ->trueLabel('Attendance hours were recalculated')
                        //                            ->falseLabel('Attendance was not recalculated'),

                        IconEntry::make('attendance_period_locked')
                            ->label('Period Locked')
                            ->boolean()
                            ->trueIcon('heroicon-o-lock-closed')
                            ->falseIcon('heroicon-o-lock-open')
                            ->trueColor('danger')
                            ->falseColor('gray'),
                        //                            ->trueLabel('Attendance period is locked')
                        //                            ->falseLabel('Attendance period remains unlocked'),
                    ]),

                Section::make('Audit Trail')
                    ->icon('heroicon-o-clock')
                    ->collapsible()
                    ->collapsed()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Recorded At')
                            ->dateTime('M d, Y H:i:s')
                            ->icon('heroicon-o-plus-circle'),

                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime('M d, Y H:i:s')
                            ->icon('heroicon-o-arrow-path'),
                    ]),
            ]);
    }
}
