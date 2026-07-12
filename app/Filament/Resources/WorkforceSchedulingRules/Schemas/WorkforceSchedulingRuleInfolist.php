<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceSchedulingRules\Schemas;

use App\Models\WorkforceSchedulingRule;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

class WorkforceSchedulingRuleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Rule Identification')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Rule Code')
                            ->icon('heroicon-o-hashtag')
                            ->weight('font-bold')
                            ->size(TextSize::Large)
                            ->copyable(),

                        TextEntry::make('name')
                            ->label('Rule Name')
                            ->icon('heroicon-o-document-text')
                            ->weight('font-bold')
                            ->size(TextSize::Large),

                        TextEntry::make('business.name')
                            ->label('Business')
                            ->badge()
                            ->icon('heroicon-o-building-office')
                            ->color('primary'),
                    ]),

                Section::make('Rule Configuration')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('rule_type')
                            ->label('Rule Type')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                WorkforceSchedulingRule::TYPE_MINIMUM_REST_HOURS => 'Minimum Rest Hours',
                                WorkforceSchedulingRule::TYPE_MAXIMUM_DAILY_HOURS => 'Maximum Daily Hours',
                                WorkforceSchedulingRule::TYPE_MAXIMUM_WEEKLY_HOURS => 'Maximum Weekly Hours',
                                WorkforceSchedulingRule::TYPE_MAXIMUM_CONSECUTIVE_DAYS => 'Maximum Consecutive Days',
                                default => $state,
                            })
                            ->color(fn (string $state): string => match ($state) {
                                WorkforceSchedulingRule::TYPE_MINIMUM_REST_HOURS => 'info',
                                WorkforceSchedulingRule::TYPE_MAXIMUM_DAILY_HOURS => 'warning',
                                WorkforceSchedulingRule::TYPE_MAXIMUM_WEEKLY_HOURS => 'danger',
                                WorkforceSchedulingRule::TYPE_MAXIMUM_CONSECUTIVE_DAYS => 'primary',
                                default => 'gray',
                            })
                            ->icon(fn (string $state): string => match ($state) {
                                WorkforceSchedulingRule::TYPE_MINIMUM_REST_HOURS => 'heroicon-o-moon',
                                WorkforceSchedulingRule::TYPE_MAXIMUM_DAILY_HOURS => 'heroicon-o-sun',
                                WorkforceSchedulingRule::TYPE_MAXIMUM_WEEKLY_HOURS => 'heroicon-o-calendar',
                                WorkforceSchedulingRule::TYPE_MAXIMUM_CONSECUTIVE_DAYS => 'heroicon-o-calendar-days',
                                default => 'heroicon-o-question-mark-circle',
                            }),

                        TextEntry::make('value_display')
                            ->label('Limit Value')
                            ->state(function (WorkforceSchedulingRule $record): string {
                                if ($record->value_decimal !== null) {
                                    return number_format((float) $record->value_decimal, 2).' hours';
                                }
                                if ($record->value_integer !== null) {
                                    return $record->value_integer.' days';
                                }

                                return 'Not set';
                            })
                            ->icon('heroicon-o-scale')
                            ->weight('font-bold')
                            ->size(TextSize::Large),

                        TextEntry::make('severity')
                            ->label('Severity')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'warning' => 'warning',
                                'error' => 'danger',
                                'critical' => 'primary',
                                default => 'gray',
                            })
                            ->icon(fn (string $state): string => match ($state) {
                                'warning' => 'heroicon-o-exclamation-triangle',
                                'error' => 'heroicon-o-no-symbol',
                                'critical' => 'heroicon-o-shield-exclamation',
                                default => 'heroicon-o-question-mark-circle',
                            }),
                    ]),

                Section::make('Scope')
                    ->icon('heroicon-o-building-office-2')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('department.name')
                            ->label('Department')
                            ->icon('heroicon-o-users')
                            ->placeholder('All departments')
                            ->weight('font-medium'),

                        TextEntry::make('workCenter.name')
                            ->label('Work Center')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->placeholder('All work centers')
                            ->weight('font-medium'),

                        TextEntry::make('employeeShift.name')
                            ->label('Employee Shift')
                            ->icon('heroicon-o-clock')
                            ->placeholder('All shifts')
                            ->weight('font-medium'),
                    ]),

                Section::make('Validity Period')
                    ->icon('heroicon-o-calendar')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('effective_from')
                            ->label('Effective From')
                            ->date('F j, Y')
                            ->icon('heroicon-o-calendar-days'),

                        TextEntry::make('effective_to')
                            ->label('Effective To')
                            ->date('F j, Y')
                            ->placeholder('No end date (ongoing)')
                            ->icon('heroicon-o-calendar-days'),

                        IconEntry::make('is_active')
                            ->label('Status')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger')
                            ->trueLabel('Active — enforced in scheduling')
                            ->falseLabel('Inactive — ignored by scheduler'),
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
                    ]),
            ]);
    }
}
