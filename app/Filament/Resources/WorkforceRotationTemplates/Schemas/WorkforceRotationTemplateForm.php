<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRotationTemplates\Schemas;

use App\Models\WorkforceRotationTemplate;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class WorkforceRotationTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                self::makeMainColumn(),
                self::makeSidebarColumn(),
            ])
            ->columns(3);
    }

    private static function makeMainColumn(): Group
    {
        return Group::make()
            ->schema([
                self::makeBasicInfoSection(),
                self::makeCycleConfigurationSection(),
                self::makeDescriptionSection(),
            ])
            ->columnSpan(['lg' => 2]);
    }

    private static function makeSidebarColumn(): Group
    {
        return Group::make()
            ->schema([
                self::makeStatusSection(),
                self::makeDaysSummarySection(),
            ])
            ->columnSpan(['lg' => 1]);
    }

    // ==================== BASIC INFO ====================

    private static function makeBasicInfoSection(): Section
    {
        return Section::make('Template Identity')
            ->description('Define the rotation pattern')
            ->icon('heroicon-o-document-text')
            ->schema([
                Grid::make(2)->schema([
                    self::makeCodeField(),
                    self::makeNameField(),
                ]),
            ]);
    }

    private static function makeCodeField(): TextInput
    {
        return TextInput::make('code')
            ->label('Template Code')
            ->required()
            ->maxLength(20)
            ->unique(ignoreRecord: true)
            ->placeholder('e.g., ROT-4WEEK-A')
            ->helperText('Unique identifier for this template');
    }

    private static function makeNameField(): TextInput
    {
        return TextInput::make('name')
            ->label('Template Name')
            ->required()
            ->maxLength(100)
            ->placeholder('e.g., 4-Week Rolling Roster Pattern A')
            ->columnSpanFull();
    }

    // ==================== CYCLE CONFIGURATION ====================

    private static function makeCycleConfigurationSection(): Section
    {
        return Section::make('Cycle Configuration')
            ->description('Define the rotation cycle length and validity period')
            ->icon('heroicon-o-arrow-path')
            ->schema([
                Grid::make(2)->schema([
                    self::makeCycleLengthField(),
                    self::makeIsActiveToggle(),
                    self::makeEffectiveFromField(),
                    self::makeEffectiveToField(),
                ]),

                Placeholder::make('cycle_info')
                    ->label('Cycle Information')
                    ->content(function ($state, Get $get): string {
                        $days = (int) ($get('cycle_length_days') ?? 0);
                        if ($days <= 0) {
                            return 'Set cycle length to see details';
                        }

                        $weeks = number_format($days / 7, 1);

                        return "📅 {$days} days = {$weeks} weeks per full rotation cycle";
                    })
                    ->visible(fn (Get $get) => ($get('cycle_length_days') ?? 0) > 0),
            ]);
    }

    private static function makeCycleLengthField(): TextInput
    {
        return TextInput::make('cycle_length_days')
            ->label('Cycle Length (Days)')
            ->required()
            ->numeric()
            ->minValue(1)
            ->maxValue(366)
            ->default(7)
            ->suffix('days')
            ->helperText('Total days in one complete rotation cycle')
            ->live(onBlur: true);
    }

    private static function makeIsActiveToggle(): Toggle
    {
        return Toggle::make('is_active')
            ->label('Active Template')
            ->default(true)
            ->inline(false)
            ->helperText('Inactive templates cannot be assigned to employees');
    }

    private static function makeEffectiveFromField(): DatePicker
    {
        return DatePicker::make('effective_from')
            ->label('Effective From')
            ->nullable()
            ->minDate(now()->subYear())
            ->helperText('When this template becomes available (optional)');
    }

    private static function makeEffectiveToField(): DatePicker
    {
        return DatePicker::make('effective_to')
            ->label('Effective To')
            ->nullable()
            ->minDate(fn (Get $get) => $get('effective_from'))
            ->helperText('Leave blank for indefinite validity');
    }

    // ==================== DESCRIPTION ====================

    private static function makeDescriptionSection(): Section
    {
        return Section::make('Description & Notes')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->collapsed()
            ->schema([
                Textarea::make('description')
                    ->label('Template Description')
                    ->rows(4)
                    ->placeholder('Describe this rotation pattern, when to use it, etc...'),
            ]);
    }

    // ==================== STATUS SIDEBAR ====================

    private static function makeStatusSection(): Section
    {
        return Section::make('Template Status')
            ->icon('heroicon-o-shield-check')
            ->schema([
                Placeholder::make('status_display')
                    ->label('Current Status')
                    ->content(function (?WorkforceRotationTemplate $record): string {
                        if (! $record) {
                            return '📝 New Template';
                        }

                        if (! $record->is_active) {
                            return '⚪ Inactive';
                        }
                        if ($record->effective_from && Carbon::parse($record->effective_from)->isFuture()) {
                            return '🟡 Scheduled';
                        }

                        return '🟢 Active';
                    })
                    ->badge()
                    ->color(function (?WorkforceRotationTemplate $record): string {
                        if (! $record) {
                            return 'info';
                        }
                        if (! $record->is_active) {
                            return 'gray';
                        }
                        if ($record->effective_from && Carbon::parse($record->effective_from)->isFuture()) {
                            return 'warning';
                        }

                        return 'success';
                    }),

                Placeholder::make('day_count')
                    ->label('Configured Days')
                    ->content(function (?WorkforceRotationTemplate $record): string {
                        if (! $record) {
                            return '—';
                        }

                        $count = $record->days()->count();
                        $total = $record->cycle_length_days ?? 0;

                        return "{$count} of {$total} configured";
                    })
                    ->badge()
                    ->color(function (?WorkforceRotationTemplate $record): string {
                        if (! $record) {
                            return 'gray';
                        }

                        $count = $record->days()->count();
                        $total = $record->cycle_length_days ?? 0;

                        if ($count === 0) {
                            return 'danger';
                        }
                        if ($count < $total) {
                            return 'warning';
                        }

                        return 'success';
                    }),
            ]);
    }

    private static function makeDaysSummarySection(): Section
    {
        return Section::make('Quick Reference')
            ->icon('heroicon-o-light-bulb')
            ->collapsed()
            ->schema([
                TextEntry::make('usage_tip')
                    ->hiddenLabel()
                    ->state('💡 **Tip:** After creating this template, configure each day\'s shift assignment in the "Days" relation manager.')
                    ->markdown()
                    ->size('sm'),
            ])
            ->visible(fn (?WorkforceRotationTemplate $record): bool => $record === null);
    }
}
