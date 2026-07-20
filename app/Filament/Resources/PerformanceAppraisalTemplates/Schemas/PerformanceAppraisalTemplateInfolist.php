<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalTemplates\Schemas;

use App\Models\PerformanceAppraisalTemplate;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

class PerformanceAppraisalTemplateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template Identification')
                    ->icon('heroicon-o-document-text')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Template Code')
                            ->icon('heroicon-o-hashtag')
                            ->weight('font-bold')
                            ->size(TextSize::Large)
                            ->copyable(),

                        TextEntry::make('name')
                            ->label('Template Name')
                            ->icon('heroicon-o-clipboard-document-check')
                            ->weight('font-bold')
                            ->size(TextSize::Large),

                        TextEntry::make('business.name')
                            ->label('Business')
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

                Section::make('Applicability')
                    ->icon('heroicon-o-users')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('department.name')
                            ->label('Department')
                            ->icon('heroicon-o-users')
                            ->placeholder('All departments')
                            ->weight('font-medium'),

                        TextEntry::make('position.name')
                            ->label('Position')
                            ->icon('heroicon-o-briefcase')
                            ->placeholder('All positions')
                            ->weight('font-medium'),

                        TextEntry::make('grade.name')
                            ->label('Grade')
                            ->icon('heroicon-o-chart-bar')
                            ->placeholder('All grades')
                            ->weight('font-medium'),

                        TextEntry::make('applicable_employment_type')
                            ->label('Employment Types')
                            ->icon('heroicon-o-user-group')
                            ->placeholder('All types')
                            ->formatStateUsing(function (?string $state): string {
                                if (empty($state)) {
                                    return 'All employment types';
                                }
                                $types = json_decode($state, true) ?? [];

                                return collect($types)->map(fn ($t) => ucwords(str_replace('_', ' ', $t)))->implode(', ');
                            }),
                    ]),

                Section::make('Rating Scale')
                    ->icon('heroicon-o-scale')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('scale.name')
                            ->label('Rating Scale')
                            ->icon('heroicon-o-scale')
                            ->weight('font-bold')
                            ->size(TextSize::Large),

                        TextEntry::make('scale_range')
                            ->label('Scale Range')
                            ->icon('heroicon-o-arrows-right-left')
                            ->state(function (PerformanceAppraisalTemplate $record): string {
                                $scale = $record->scale;
                                if (! $scale) {
                                    return 'Not configured';
                                }

                                return number_format((float) $scale->minimum_score, $scale->decimal_places)
                                    .' – '
                                    .number_format((float) $scale->maximum_score, $scale->decimal_places);
                            })
                            ->fontFamily('font-mono'),
                    ]),

                Section::make('Component Weights')
                    ->icon('heroicon-o-calculator')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('goal_weight_percent')
                            ->label('Goal Weight')
                            ->icon('heroicon-o-bullseye')
                            ->color('success')
                            ->weight('font-bold')
                            ->size(TextSize::Large)
                            ->formatStateUsing(fn (float $state): string => number_format($state, 2).'%'),

                        TextEntry::make('competency_weight_percent')
                            ->label('Competency Weight')
                            ->icon('heroicon-o-academic-cap')
                            ->color('warning')
                            ->weight('font-bold')
                            ->size(TextSize::Large)
                            ->formatStateUsing(fn (float $state): string => number_format($state, 2).'%'),

                        TextEntry::make('other_weight_percent')
                            ->label('Other Weight')
                            ->icon('heroicon-o-puzzle-piece')
                            ->color('info')
                            ->weight('font-bold')
                            ->size(TextSize::Large)
                            ->formatStateUsing(fn (float $state): string => number_format($state, 2).'%'),

                        TextEntry::make('weight_total')
                            ->label('Total Weight')
                            ->icon('heroicon-o-calculator')
                            ->state(function (PerformanceAppraisalTemplate $record): string {
                                $total = (float) $record->goal_weight_percent
                                    + (float) $record->competency_weight_percent
                                    + (float) $record->other_weight_percent;

                                return number_format($total, 2).'%';
                            })
                            ->color(function (PerformanceAppraisalTemplate $record): string {
                                $total = (float) $record->goal_weight_percent
                                    + (float) $record->competency_weight_percent
                                    + (float) $record->other_weight_percent;

                                return abs($total - 100.0) < 0.0001 ? 'success' : 'danger';
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Comment Requirements')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->columns(3)
                    ->schema([
                        IconEntry::make('require_self_comment')
                            ->label('Self Comment')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('gray'),
                        //                            ->trueLabel('Required')
                        //                            ->falseLabel('Optional'),

                        IconEntry::make('require_manager_comment')
                            ->label('Manager Comment')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('gray'),
                        //                            ->trueLabel('Required')
                        //                            ->falseLabel('Optional'),

                        IconEntry::make('require_final_comment')
                            ->label('Final Comment')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('gray'),
                        //                            ->trueLabel('Required')
                        //                            ->falseLabel('Optional'),
                    ]),

                Section::make('Validity & Version')
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

                        TextEntry::make('version')
                            ->label('Version')
                            ->icon('heroicon-o-tag')
                            ->suffix('v')
                            ->weight('font-bold'),

                        IconEntry::make('allow_not_applicable')
                            ->label('Allow N/A')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('gray'),

                        IconEntry::make('is_active')
                            ->label('Active Status')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger')
                            ->trueLabel('Active — available for appraisals')
                            ->falseLabel('Inactive — hidden from selection'),
                    ]),

                Section::make('Sections')
                    ->icon('heroicon-o-list-bullet')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('sections_count')
                            ->label('Configured Sections')
                            ->state(fn (PerformanceAppraisalTemplate $record): int => $record->sections()->count())
                            ->icon('heroicon-o-document')
                            ->suffix(' sections'),
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
