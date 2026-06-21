<?php

namespace App\Filament\Resources\SocialSecurityTiers\Schemas;

use App\Models\SocialSecurityTier;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SocialSecurityTierInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tier Details')
                    ->schema([
                        TextEntry::make('tier_code')->badge()->color('primary'),
                        TextEntry::make('code')->label('Mapping Code')->placeholder('-'),
                        TextEntry::make('from_salary')->label('From Salary')->money()->placeholder('-'),
                        TextEntry::make('to_salary')->label('To Salary')->money()->placeholder('Unlimited'),
                    ])->columns(2),

                Section::make('Contribution Rules')
                    ->schema([
                        TextEntry::make('employee_rate')->label('Employee Rate')->suffix('%'),
                        TextEntry::make('employer_rate')->label('Employer Rate')->suffix('%'),
                        TextEntry::make('max_base')->label('Max Base')->money()->placeholder('-'),
                        TextEntry::make('employee_max_amount')->label('Employee Cap')->money()->placeholder('-'),
                        TextEntry::make('employer_max_amount')->label('Employer Cap')->money()->placeholder('-'),
                    ])->columns(3),

                Section::make('Payroll Usage')
                    ->schema([
                        TextEntry::make('payroll_usage')
                            ->label('Used By Payroll')
                            ->state('PayrollCalculationService::calculateSocialSecurity()')
                            ->badge()
                            ->color('info'),
                        TextEntry::make('payroll_codes')
                            ->label('Payroll Codes')
                            ->state(fn (SocialSecurityTier $record): string => implode(', ', array_filter([$record->tier_code, $record->code])))
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('usage_note')
                            ->label('Note')
                            ->state('This tier is consumed when payroll calculates NSSF / NHIF / SHIF deductions.')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }
}
