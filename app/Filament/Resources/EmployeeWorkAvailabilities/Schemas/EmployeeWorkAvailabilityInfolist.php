<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeWorkAvailabilities\Schemas;

use App\Models\EmployeeWorkAvailability;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

class EmployeeWorkAvailabilityInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Availability Overview')
                    ->icon('heroicon-o-user')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('employee.full_name')
                            ->label('Employee')
                            ->icon('heroicon-o-user')
                            ->weight('font-bold')
                            ->size(TextSize::Large),

                        TextEntry::make('availability_type')
                            ->badge()
                            ->label('Type')
                            ->formatStateUsing(fn (string $state): string => self::getAvailabilityTypeOptions()[$state] ?? $state
                            )
                            ->color(fn (string $state): string => match ($state) {
                                EmployeeWorkAvailability::TYPE_AVAILABLE => 'success',
                                EmployeeWorkAvailability::TYPE_UNAVAILABLE => 'danger',
                                EmployeeWorkAvailability::TYPE_PREFERRED_SHIFT => 'info',
                                EmployeeWorkAvailability::TYPE_RESTRICTED_SHIFT => 'warning',
                                EmployeeWorkAvailability::TYPE_OFFICIAL_DUTY => 'primary',
                                default => 'gray',
                            }),

                        TextEntry::make('status')
                            ->color(fn (string $state): string => match ($state) {
                                EmployeeWorkAvailability::STATUS_DRAFT => 'gray',
                                EmployeeWorkAvailability::STATUS_SUBMITTED => 'warning',
                                EmployeeWorkAvailability::STATUS_APPROVED => 'success',
                                EmployeeWorkAvailability::STATUS_REJECTED => 'danger',
                                EmployeeWorkAvailability::STATUS_CANCELLED => 'secondary',
                            }),
                    ]),

                Section::make('Date Range')
                    ->icon('heroicon-o-calendar')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('date_from')
                            ->label('From')
                            ->date('F j, Y')
                            ->icon('heroicon-o-calendar-days'),

                        TextEntry::make('date_to')
                            ->label('To')
                            ->date('F j, Y')
                            ->icon('heroicon-o-calendar-days'),

                        TextEntry::make('duration')
                            ->label('Duration')
                            ->state(function (EmployeeWorkAvailability $record): string {
                                $days = $record->date_from->diffInDays($record->date_to) + 1;

                                return $days === 1 ? '1 day' : "{$days} days";
                            })
                            ->icon('heroicon-o-clock'),
                    ]),

                Section::make('Details')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        IconEntry::make('is_confidential')
                            ->label('Confidential')
                            ->boolean()
                            ->trueIcon('heroicon-o-lock-closed')
                            ->falseIcon('heroicon-o-lock-open')
                            ->trueColor('warning'),

                        TextEntry::make('reason')
                            ->label('Reason / Notes')
                            ->markdown()
                            ->prose()
                            ->placeholder('No reason provided')
                            ->columnSpanFull(),
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

                        TextEntry::make('requested_by')
                            ->label('Requested By')
                            ->placeholder('System')
                            ->icon('heroicon-o-user-circle'),
                    ]),
            ]);
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
}
