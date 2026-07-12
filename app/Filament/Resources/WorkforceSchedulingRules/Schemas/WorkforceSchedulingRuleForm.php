<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceSchedulingRules\Schemas;

use App\Models\EmployeeShift;
use App\Models\WorkforceSchedulingRule;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class WorkforceSchedulingRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Rule Identification')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('code')
                                    ->label('Rule Code')
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true)
                                    ->placeholder('e.g. MAX-DAILY-12H')
                                    ->prefixIcon('heroicon-m-hashtag'),

                                TextInput::make('name')
                                    ->label('Rule Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g. Maximum 12 Hours Per Day')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                                        if (empty($get('code')) && ! empty($state)) {
                                            $set('code', strtoupper(str_replace([' ', '-'], '_', $state)));
                                        }
                                    }),

                                Select::make('business_id')
                                    ->label('Business')
                                    ->relationship('business', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set) {
                                        $set('department_id', null);
                                        $set('work_center_id', null);
                                        $set('employee_shift_id', null);
                                    }),
                            ]),
                    ]),

                Section::make('Rule Configuration')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('rule_type')
                                    ->label('Rule Type')
                                    ->options([
                                        WorkforceSchedulingRule::TYPE_MINIMUM_REST_HOURS => 'Minimum Rest Hours',
                                        WorkforceSchedulingRule::TYPE_MAXIMUM_DAILY_HOURS => 'Maximum Daily Hours',
                                        WorkforceSchedulingRule::TYPE_MAXIMUM_WEEKLY_HOURS => 'Maximum Weekly Hours',
                                        WorkforceSchedulingRule::TYPE_MAXIMUM_CONSECUTIVE_DAYS => 'Maximum Consecutive Days',
                                    ])
                                    ->required()
                                    ->native(false)
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                                        if ($state === WorkforceSchedulingRule::TYPE_MINIMUM_REST_HOURS) {
                                            $set('value_integer', null);
                                        } else {
                                            $set('value_decimal', null);
                                        }
                                    }),

                                Select::make('severity')
                                    ->label('Severity')
                                    ->options([
                                        'warning' => 'Warning — Logged but allowed',
                                        'error' => 'Error — Blocks scheduling',
                                        'critical' => 'Critical — Alerts management',
                                    ])
                                    ->required()
                                    ->native(false)
                                    ->default('error')
                                    ->hintIcon('heroicon-m-shield-exclamation'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('value_decimal')
                                    ->label('Value (Decimal)')
                                    ->numeric()
//                                    ->decimalPlaces(4)
                                    ->minValue(0)
                                    ->step(0.5)
                                    ->suffix('hours')
                                    ->visible(fn (Get $get): bool => in_array($get('rule_type'), [
                                        WorkforceSchedulingRule::TYPE_MINIMUM_REST_HOURS,
                                        WorkforceSchedulingRule::TYPE_MAXIMUM_DAILY_HOURS,
                                        WorkforceSchedulingRule::TYPE_MAXIMUM_WEEKLY_HOURS,
                                    ]))
                                    ->required(fn (Get $get): bool => in_array($get('rule_type'), [
                                        WorkforceSchedulingRule::TYPE_MINIMUM_REST_HOURS,
                                        WorkforceSchedulingRule::TYPE_MAXIMUM_DAILY_HOURS,
                                        WorkforceSchedulingRule::TYPE_MAXIMUM_WEEKLY_HOURS,
                                    ]))
                                    ->placeholder(fn (Get $get): string => match ($get('rule_type')) {
                                        WorkforceSchedulingRule::TYPE_MINIMUM_REST_HOURS => 'e.g. 11.0',
                                        WorkforceSchedulingRule::TYPE_MAXIMUM_DAILY_HOURS => 'e.g. 12.0',
                                        WorkforceSchedulingRule::TYPE_MAXIMUM_WEEKLY_HOURS => 'e.g. 48.0',
                                        default => 'Enter value',
                                    }),

                                TextInput::make('value_integer')
                                    ->label('Value (Integer)')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->suffix('days')
                                    ->visible(fn (Get $get): bool => $get('rule_type') === WorkforceSchedulingRule::TYPE_MAXIMUM_CONSECUTIVE_DAYS)
                                    ->required(fn (Get $get): bool => $get('rule_type') === WorkforceSchedulingRule::TYPE_MAXIMUM_CONSECUTIVE_DAYS)
                                    ->placeholder('e.g. 6'),
                            ]),
                    ]),

                Section::make('Scope')
                    ->icon('heroicon-o-building-office-2')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('department_id')
                                    ->label('Department')
                                    ->relationship('department', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set) {
                                        $set('work_center_id', null);
                                    })
                                    ->placeholder('All departments'),

                                Select::make('work_center_id')
                                    ->label('Work Center')
                                    ->relationship('workCenter', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->placeholder('All work centers'),

                                Select::make('employee_shift_id')
                                    ->label('Employee Shift')
                                    ->relationship('employeeShift', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->placeholder('All shifts')
                                    ->helperText(fn (?int $state): ?string => $state
                                        ? EmployeeShift::find($state)?->start_time.' – '.EmployeeShift::find($state)?->end_time
                                        : null
                                    ),
                            ]),
                    ]),

                Section::make('Validity')
                    ->icon('heroicon-o-calendar')
                    ->columns(3)
                    ->schema([
                        DatePicker::make('effective_from')
                            ->label('Effective From')
                            ->required()
                            ->native(false)
                            ->displayFormat('M d, Y')
                            ->default(now())
                            ->closeOnDateSelection()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                                $effectiveTo = $get('effective_to');
                                if ($effectiveTo && $state && Carbon::parse($state)->gt(Carbon::parse($effectiveTo))) {
                                    $set('effective_to', null);
                                }
                            }),

                        DatePicker::make('effective_to')
                            ->label('Effective To')
                            ->native(false)
                            ->displayFormat('M d, Y')
                            ->closeOnDateSelection()
                            ->minDate(fn (Get $get): ?string => $get('effective_from'))
                            ->afterOrEqual('effective_from')
                            ->validationMessages([
                                'after_or_equal' => 'The end date must be on or after the start date.',
                            ])
                            ->placeholder('No end date'),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->inline(false)
                            ->default(true)
                            ->onIcon('heroicon-o-check')
                            ->offIcon('heroicon-o-x-mark'),
                    ]),
            ]);
    }
}
