<?php

namespace App\Filament\Resources\EmployeePostingGroups\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeePostingGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->description('Define the identification and usage status for this employee posting group.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Group Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->placeholder('e.g., FULL-TIME'),

                        TextInput::make('description')
                            ->label('Description')
                            ->maxLength(255)
                            ->placeholder('e.g., Full-time staff accounts payable group'),

                        Toggle::make('blocked')
                            ->label('Blocked from usage')
                            ->helperText('If enabled, this group cannot be assigned to new employees or used in journals.')
                            ->default(false)
                            ->inline(false),
                    ]),

                Section::make('Account Setup')
                    ->description('Configure the primary G/L account for employee transactions.')
                    ->schema([
                        Select::make('payables_account_id')
                            ->label('Employee Payables Account')
                            ->relationship('payablesAccount', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('The G/L account used for employee salaries, reimbursements, and payables.'),
                    ]),
            ]);
    }
}
