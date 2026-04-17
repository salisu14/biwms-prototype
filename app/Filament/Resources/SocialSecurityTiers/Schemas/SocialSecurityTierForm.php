<?php

namespace App\Filament\Resources\SocialSecurityTiers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SocialSecurityTierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tier Identification')
                    ->description('Primary classification codes for the social security tier.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('tier_code')
                            ->label('Tier Code')
                            ->required()
                            ->maxLength(20)
                            ->placeholder('e.g., TIER-1'),

                        TextInput::make('code')
                            ->label('External / Mapping Code')
                            ->maxLength(50)
                            ->placeholder('e.g., SS-CORP-01'),
                    ]),

                Section::make('Salary Thresholds')
                    ->description('Define the income range applicable to this tier.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('from_salary')
                            ->label('Salary From')
                            ->required()
                            ->numeric()
                            ->prefix('$')
                            ->placeholder('0.00'),

                        TextInput::make('to_salary')
                            ->label('Salary To')
                            ->numeric()
                            ->prefix('$')
                            ->placeholder('Leave empty for no upper limit'),
                    ]),

                Section::make('Contribution Rates')
                    ->description('Set the percentage rates for both employee and employer.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('employee_rate')
                            ->label('Employee Contribution Rate')
                            ->required()
                            ->numeric()
                            ->suffix('%')
                            ->placeholder('e.g., 5.00'),

                        TextInput::make('employer_rate')
                            ->label('Employer Contribution Rate')
                            ->required()
                            ->numeric()
                            ->suffix('%')
                            ->placeholder('e.g., 10.00'),
                    ]),

                Section::make('Calculated Caps & Maximums')
                    ->description('Define limits to prevent over-contribution.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('max_base')
                            ->label('Max Salary Base')
                            ->numeric()
                            ->prefix('$')
                            ->helperText('Maximum salary amount subject to calculation.'),

                        TextInput::make('employee_max_amount')
                            ->label('Employee Max Cap')
                            ->numeric()
                            ->prefix('$')
                            ->helperText('Maximum contribution for employee.'),

                        TextInput::make('employer_max_amount')
                            ->label('Employer Max Cap')
                            ->numeric()
                            ->prefix('$')
                            ->helperText('Maximum contribution for employer.'),
                    ]),
            ]);
    }
}
