<?php

declare(strict_types=1);

namespace App\Filament\Resources\Referrers\Schemas;

use App\Models\Referrer;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReferrerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make([
                    'default' => 1,
                    'xl' => 2,
                ])->schema([
                    Section::make('General Information')
                        ->columns(2)
                        ->schema([
                            TextEntry::make('code')->weight('bold')->copyable(),
                            TextEntry::make('name')->weight('bold'),
                            TextEntry::make('type')->badge()->color(fn ($state) => $state?->color()),
                            TextEntry::make('linked_entity')
                                ->label('Linked Entity')
                                ->state(fn (Referrer $record): string => $record->linkedEntityLabel()),
                            IconEntry::make('is_active')->boolean(),
                            IconEntry::make('commission_eligible')->boolean(),
                        ]),

                    Section::make('Contact Details')
                        ->columns(2)
                        ->schema([
                            TextEntry::make('phone')->icon('heroicon-m-phone')->placeholder('—'),
                            TextEntry::make('email')->icon('heroicon-m-envelope')->copyable()->placeholder('—'),
                            TextEntry::make('city')->placeholder('—'),
                            TextEntry::make('state')->placeholder('—'),
                            TextEntry::make('country')->placeholder('—'),
                            TextEntry::make('address')->columnSpanFull()->placeholder('—'),
                        ]),
                ]),

                Section::make('Notes')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('notes')->markdown()->placeholder('No notes recorded.'),
                    ]),
            ]);
    }
}
