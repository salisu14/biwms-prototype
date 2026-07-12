<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRotationTemplates\Schemas;

use App\Models\WorkforceRotationTemplate;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkforceRotationTemplateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([self::makeGrid()]);
    }

    private static function makeGrid(): Grid
    {
        return Grid::make(3)->schema([
            self::identityColumn(), self::configurationColumn(), self::summaryColumn(),
        ]);
    }

    private static function identityColumn(): Group
    {
        return Group::make([
            Section::make('Template Overview')->icon('heroicon-o-document-text')->schema([
                Grid::make(2)->schema([
                    TextEntry::make('code')->label('Code')->weight('bold')->size('lg')
                        ->copyable()->copyMessage('Copied!'),
                    TextEntry::make('name')->label('Name')->weight('medium')
                        ->columnSpanFull(),
                    IconEntry::make('is_active')->label('Active')->boolean()
                        ->trueIcon('heroicon-o-check-circle')->trueColor('success')
                        ->falseIcon('heroicon-o-x-circle')->falseColor('gray'),
                    TextEntry::make('created_at')->label('Created')
                        ->dateTime('M d, Y')->since()->icon('heroicon-o-plus-circle')->size('sm'),
                ]),
            ]),

            Section::make('Description')->icon('heroicon-o-chat-bubble-left-right')
                ->collapsed()
                ->visible(fn (WorkforceRotationTemplate $t): bool => ! empty($t->description))
                ->schema([
                    TextEntry::make('description')->markdown()->columnSpanFull(),
                ]),
        ]);
    }

    private static function configurationColumn(): Group
    {
        return Group::make([
            Section::make('Cycle Configuration')->icon('heroicon-o-arrow-path')->schema([
                Grid::make(1)->schema([
                    TextEntry::make('cycle_length_days')->label('Cycle Length')
                        ->formatStateUsing(fn (int $s): string => "**{$s} Days** (".number_format($s / 7, 1).' weeks)'
                        )->size('lg')->weight('bold')->markdown()
                        ->icon('heroicon-o-calendar-days'),

                    TextEntry::make('effective_dates')->label('Validity Period')
                        ->getStateUsing(function (WorkforceRotationTemplate $t): string {
                            $from = $t->effective_from?->format('M d, Y') ?? 'Unlimited';
                            $to = $t->effective_to?->format('M d, Y') ?? 'Indefinite';

                            return "{$from} → {$to}";
                        })->icon('heroicon-o-clock'),
                ]),
            ]),

            Section::make('Day Configuration Summary')->icon('heroicon-o-list-bullet')->schema([
                TextEntry::make('days_configured')->label('Progress')
                    ->getStateUsing(function (WorkforceRotationTemplate $t): string {
                        $configured = $t->days()->count();
                        $total = $t->cycle_length_days;
                        $pct = $total > 0 ? round(($configured / $total) * 100) : 0;

                        return "{$configured}/{$total} days ({$pct}%)";
                    })
                    ->badge()
                    ->color(fn (WorkforceRotationTemplate $t): string => $t->days()->count() >= $t->cycle_length_days ? 'success' : 'warning'
                    )
                    ->size('lg'),

                TextEntry::make('rest_days_count')->label('Rest Days Configured')
                    ->getStateUsing(fn (WorkforceRotationTemplate $t): int => $t->days()->where('is_rest_day', true)->count()
                    )
                    ->formatStateUsing(fn (int $s): string => "{$s} rest day(s)")
                    ->icon('heroicon-o-moon'),

                TextEntry::make('unique_shifts')->label('Unique Shifts Used')
                    ->getStateUsing(function (WorkforceRotationTemplate $t): int {
                        return $t->days()->whereNotNull('employee_shift_id')
                            ->distinct('employee_shift_id')->count('employee_shift_id');
                    })
                    ->formatStateUsing(fn (int $s): string => $s > 0 ? "{$s} shift(s)" : 'None')
                    ->icon('heroicon-o-clock'),
            ]),
        ]);
    }

    private static function summaryColumn(): Group
    {
        return Group::make([
            Section::make('System Info')->icon('heroicon-o-information-circle')->collapsed()->schema([
                Grid::make(2)->schema([
                    TextEntry::make('id')->label('ID')->copyable()->icon('heroicon-o-fingerprint')
                        ->size('sm')->color('gray'),
                    TextEntry::make('updated_at')->label('Updated')
                        ->dateTime('M d, Y g:i A')->since()->icon('heroicon-o-arrow-path')->size('sm'),
                ]),
            ]),
        ]);
    }
}
