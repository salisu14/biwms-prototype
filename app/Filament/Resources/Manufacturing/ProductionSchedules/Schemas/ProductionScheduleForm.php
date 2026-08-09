<?php

declare(strict_types=1);

namespace App\Filament\Resources\Manufacturing\ProductionSchedules\Schemas;

use App\Enums\ProductionSchedulingMode;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductionScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Planning Run')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        TextInput::make('schedule_no')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn (): string => 'APS-'.now()->format('YmdHis')),
                        TextInput::make('name')
                            ->maxLength(255),
                        DateTimePicker::make('horizon_start_at')
                            ->required()
                            ->seconds(false)
                            ->default(now()->startOfHour()),
                        DateTimePicker::make('horizon_end_at')
                            ->required()
                            ->seconds(false)
                            ->default(now()->addWeek()->endOfHour()),
                        Select::make('scheduling_mode')
                            ->options([
                                ProductionSchedulingMode::Forward->value => 'Forward',
                                ProductionSchedulingMode::Backward->value => 'Backward',
                            ])
                            ->required()
                            ->default(ProductionSchedulingMode::Forward->value),
                        TextInput::make('freeze_horizon_minutes')
                            ->numeric()
                            ->required()
                            ->default(480),
                        Textarea::make('notes')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
