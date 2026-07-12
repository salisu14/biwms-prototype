<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeWorkAvailabilities\Schemas;

use App\Models\EmployeeWorkAvailability;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class EmployeeWorkAvailabilityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Section::make('Availability Details')
                    ->icon('heroicon-o-calendar-days')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('employee_id')
                                    ->relationship('employee', 'full_name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->placeholder('Select an employee'),

                                Select::make('availability_type')
                                    ->label('Availability Type')
                                    ->options(self::getAvailabilityTypeOptions())
                                    ->required()
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->placeholder('Select type'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                DatePicker::make('date_from')
                                    ->label('From Date')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('M d, Y')
                                    ->closeOnDateSelection()
                                    ->minDate(now()->subYear())
                                    ->maxDate(now()->addYears(2))
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                                        $dateTo = $get('date_to');
                                        if ($dateTo && $state && Carbon::parse($state)->gt(Carbon::parse($dateTo))) {
                                            $set('date_to', null);
                                        }
                                    }),

                                DatePicker::make('date_to')
                                    ->label('To Date')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('M d, Y')
                                    ->closeOnDateSelection()
                                    ->minDate(fn (Get $get): ?string => $get('date_from'))
                                    ->afterOrEqual('date_from')
                                    ->validationMessages([
                                        'after_or_equal' => 'The end date must be on or after the start date.',
                                    ]),
                            ]),

                        Grid::make(3)
                            ->schema([
                                Select::make('status')
                                    ->options(self::getStatusOptions())
                                    ->default(EmployeeWorkAvailability::STATUS_DRAFT)
                                    ->required()
                                    ->native(false)
                                    ->disabled(fn (string $operation): bool => $operation === 'create'),

                                Toggle::make('is_confidential')
                                    ->label('Confidential Record')
                                    ->inline(false)
                                    ->hintIcon('heroicon-m-shield-exclamation')
                                    ->hint('Mark if this contains sensitive information'),

                                TextEntry::make('duration')
                                    ->label('Duration')
                                    ->state(function (Get $get): string {
                                        $from = $get('date_from');
                                        $to = $get('date_to');

                                        if (! $from || ! $to) {
                                            return '—';
                                        }

                                        $days = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;

                                        return $days === 1 ? '1 day' : "{$days} days";
                                    }),
                            ]),

                        Textarea::make('reason')
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000)
                            ->rows(4)
                            ->placeholder('Provide a detailed reason for this availability record...')
                            ->columnSpanFull(),
                    ]),

                // Approval workflow section - only visible when editing approved/rejected records
                Section::make('Approval Information')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn (string $operation, Get $get): bool => $operation === 'edit' && in_array($get('status'), [
                        EmployeeWorkAvailability::STATUS_APPROVED,
                        EmployeeWorkAvailability::STATUS_REJECTED,
                    ])
                    )
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('approved_by')
                                    ->placeholder('Not yet approved'),

                                TextEntry::make('approved_at')
                                    ->dateTime()
                                    ->placeholder('—'),

                                TextEntry::make('rejected_by')
                                    ->placeholder('Not yet rejected'),

                                TextEntry::make('rejected_at')
                                    ->dateTime()
                                    ->placeholder('—'),
                            ]),

                        TextEntry::make('rejection_reason')
                            ->placeholder('No rejection reason provided')
                            ->columnSpanFull(),
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

    private static function getStatusOptions(): array
    {
        return [
            EmployeeWorkAvailability::STATUS_DRAFT => 'Draft',
            EmployeeWorkAvailability::STATUS_SUBMITTED => 'Submitted',
            EmployeeWorkAvailability::STATUS_APPROVED => 'Approved',
            EmployeeWorkAvailability::STATUS_REJECTED => 'Rejected',
            EmployeeWorkAvailability::STATUS_CANCELLED => 'Cancelled',
        ];
    }
}
