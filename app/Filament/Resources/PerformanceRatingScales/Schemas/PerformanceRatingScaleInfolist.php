<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceRatingScales\Schemas;

use App\Models\PerformanceRatingScale;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

class PerformanceRatingScaleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Scale Identification')
                    ->icon('heroicon-o-scale')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Scale Code')
                            ->icon('heroicon-o-hashtag')
                            ->weight('font-bold')
                            ->size(TextSize::Large)
                            ->copyable(),

                        TextEntry::make('name')
                            ->label('Scale Name')
                            ->icon('heroicon-o-document-text')
                            ->weight('font-bold')
                            ->size(TextSize::Large),

                        TextEntry::make('business.name')
                            ->label('Business')
                            ->badge()
                            ->icon('heroicon-o-building-office')
                            ->color('primary'),
                    ]),

                Section::make('Description')
                    ->icon('heroicon-o-document-text')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('description')
                            ->label('Description')
                            ->markdown()
                            ->prose()
                            ->placeholder('No description provided')
                            ->columnSpanFull(),
                    ]),

                Section::make('Score Configuration')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('minimum_score')
                            ->label('Minimum Score')
                            ->icon('heroicon-o-arrow-down-circle')
                            ->color('success')
                            ->weight('font-bold')
                            ->size(TextSize::Large)
                            ->formatStateUsing(fn (float $state, PerformanceRatingScale $record): string =>
                            number_format($state, $record->decimal_places)
                            ),

                        TextEntry::make('maximum_score')
                            ->label('Maximum Score')
                            ->icon('heroicon-o-arrow-up-circle')
                            ->color('danger')
                            ->weight('font-bold')
                            ->size(TextSize::Large)
                            ->formatStateUsing(fn (float $state, PerformanceRatingScale $record): string =>
                            number_format($state, $record->decimal_places)
                            ),

                        TextEntry::make('decimal_places')
                            ->label('Decimal Precision')
                            ->icon('heroicon-o-calculator')
                            ->formatStateUsing(fn (int $state): string => "{$state} decimal" . ($state === 1 ? '' : 's')),
                    ]),

                Section::make('Validity & Status')
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

                        IconEntry::make('is_default')
                            ->label('Default Scale')
                            ->boolean()
                            ->trueIcon('heroicon-o-star')
                            ->falseIcon('heroicon-o-minus')
                            ->trueColor('warning')
                            ->falseColor('gray')
                            ->trueLabel('This is the default scale for the business')
                            ->falseLabel('Not the default scale'),
                    ]),

                Section::make('Status')
                    ->icon('heroicon-o-flag')
                    ->columns(2)
                    ->schema([
                        IconEntry::make('is_active')
                            ->label('Active Status')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),
//                            ->trueLabel('Active — available for evaluations')
//                            ->falseLabel('Inactive — hidden from selection'),

                        TextEntry::make('levels_count')
                            ->label('Defined Levels')
                            ->state(fn (PerformanceRatingScale $record): int => $record->levels()->count())
                            ->icon('heroicon-o-list-bullet')
                            ->suffix(' levels configured'),
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
