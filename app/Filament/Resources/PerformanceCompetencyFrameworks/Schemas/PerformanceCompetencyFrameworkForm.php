<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceCompetencyFrameworks\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PerformanceCompetencyFrameworkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Framework Information')
                    ->schema([
                        Select::make('business_id')
                            ->label('Business')
                            ->relationship(
                                name: 'business',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('name')
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->nullable()
                            ->placeholder('Global / All Businesses')
                            ->helperText(
                                'Leave blank when the framework applies globally.'
                            ),

                        TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., TECH-COMP')
                            ->helperText(
                                'A unique code for this framework within the selected business.'
                            ),

                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder(
                                'e.g., Technical Competency Framework'
                            ),

                        Textarea::make('description')
                            ->label('Description')
                            ->nullable()
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Effectiveness Period')
                    ->schema([
                        DatePicker::make('effective_from')
                            ->label('Effective From')
                            ->nullable()
                            ->native(false),

                        DatePicker::make('effective_to')
                            ->label('Effective To')
                            ->nullable()
                            ->native(false)
                            ->afterOrEqual('effective_from'),
                    ])
                    ->columns(2),

                Section::make('Status')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText(
                                'Enable or disable this framework.'
                            )
                            ->default(true),
                    ]),
            ]);
    }
}
