<?php

namespace App\Filament\Resources\WorkCenterCalendars\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkCenterCalendarForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Schedule Configuration')
                    ->description('Define the working hours and capacity for this specific work center date.')
                    ->icon('heroicon-m-calendar-days')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('work_center_id')
                                    ->relationship('workCenter', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpan(2),

                                DatePicker::make('date')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
//                                    ->closeOnDateSelect()
                                    ->required()
                                    ->columnSpan(1),
                            ]),

                        Grid::make(3)
                            ->schema([
                                Toggle::make('is_working_day')
                                    ->label('Working Day')
                                    ->helperText('Disable if this is a holiday or planned shutdown.')
                                    ->default(true)
                                    ->live()
//                                    ->color([
//                                        'on' => 'success',
//                                        'off' => 'danger',
//                                    ])
                                    ->columnSpan(1),

                                TimePicker::make('start_time')
                                    ->label('Shift Start')
                                    ->seconds(false)
                                    ->visible(fn ($get) => $get('is_working_day'))
                                    ->required(fn ($get) => $get('is_working_day'))
                                    ->columnSpan(1),

                                TimePicker::make('end_time')
                                    ->label('Shift End')
                                    ->seconds(false)
                                    ->visible(fn ($get) => $get('is_working_day'))
                                    ->required(fn ($get) => $get('is_working_day'))
                                    ->after('start_time')
                                    ->columnSpan(1),
                            ]),
                    ]),

                Section::make('Capacity & Efficiency')
                    ->description('Specify the output metrics for this calendar entry.')
                    ->icon('heroicon-m-bolt')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('capacity')
                                    ->label('Daily Capacity')
                                    ->numeric()
                                    ->suffix('Hrs')
                                    ->default(0)
                                    ->required()
                                    ->minValue(0),

                                TextInput::make('efficiency')
                                    ->label('Efficiency Rate')
                                    ->numeric()
                                    ->suffix('%')
                                    ->default(100)
                                    ->required()
                                    ->minValue(0)
                                    ->maxValue(100),

                                TextInput::make('absence_code')
                                    ->label('Absence/Reason Code')
                                    ->placeholder('e.g. MAINT-01')
                                    ->hidden(fn ($get) => $get('is_working_day')),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }
}
