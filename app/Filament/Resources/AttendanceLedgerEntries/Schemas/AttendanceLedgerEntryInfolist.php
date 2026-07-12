<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceLedgerEntries\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttendanceLedgerEntryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Employee Information')
                    ->icon('heroicon-m-user-group')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('employee.employee_number')
                                ->label('Employee No.')
                                ->icon('heroicon-m-identification'),

                            TextEntry::make('employee_name')
                                ->label('Full Name')
                                ->state(fn ($record) => $record->employee?->first_name.' '.$record->employee?->last_name)
                                ->icon('heroicon-m-user')
                                ->columnSpan(2),

                            TextEntry::make('attendance_date')
                                ->date()
                                ->icon('heroicon-m-calendar-days'),
                        ]),
                    ]),

                Section::make('Time & Hours')
                    ->icon('heroicon-m-clock')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('clock_in_at')
                                ->dateTime('h:i A')
                                ->label('Clock In')
                                ->icon('heroicon-m-arrow-right-on-rectangle')
                                ->placeholder('—'),

                            TextEntry::make('clock_out_at')
                                ->dateTime('h:i A')
                                ->label('Clock Out')
                                ->icon('heroicon-m-arrow-left-on-rectangle')
                                ->placeholder('—'),

                            TextEntry::make('break_minutes')
                                ->label('Break Duration')
                                ->formatStateUsing(fn (?int $state): string => $state !== null ? "{$state} mins" : '—')
                                ->icon('heroicon-m-question-mark-circle')
                                ->placeholder('—'),

                            TextEntry::make('worked_hours')
                                ->label('Total Worked')
                                ->formatStateUsing(fn (?string $state): string => $state !== null ? "{$state} hrs" : '0.00 hrs')
                                ->icon('heroicon-m-briefcase')
                                ->weight('bold')
                                ->size('lg'),

                            TextEntry::make('status')
                                ->label('Status')
                                ->badge()
                                ->icon('heroicon-m-circle-stack')
                                ->color(fn (string $state): string => match ($state) {
                                    'APPROVED' => 'success',
                                    'REJECTED' => 'danger',
                                    'OPEN' => 'warning',
                                    default => 'gray',
                                })
                                ->formatStateUsing(fn (string $state): string => ucfirst(strtolower($state))),
                        ]),
                    ]),

                Section::make('Approval Details')
                    ->icon('heroicon-m-check-circle')
                    ->collapsible()
                    // Automatically collapse this section if the entry hasn't been approved/reviewed yet
                    ->collapsed(fn ($record) => in_array($record->status, ['OPEN', null]))
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('approver.name')
                                ->label('Approved By')
                                ->icon('heroicon-m-user-circle')
                                ->placeholder('Pending Approval'),

                            TextEntry::make('approved_at')
                                ->dateTime()
                                ->icon('heroicon-m-clock')
                                ->placeholder('—'),
                        ]),

                        TextEntry::make('approval_note')
                            ->label('Note')
                            ->icon('heroicon-m-chat-bubble-bottom-center-text')
                            ->placeholder('No note provided.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
