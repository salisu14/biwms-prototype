<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerReferrals\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerReferralInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Referral')
                ->columns(3)
                ->schema([
                    TextEntry::make('customer.name')->label('Customer')->weight('bold'),
                    TextEntry::make('referrer.name')->label('Referrer')->weight('bold'),
                    TextEntry::make('status')->badge()->color(fn ($state) => $state?->color()),
                    IconEntry::make('is_primary')->boolean(),
                    TextEntry::make('referred_at')->date(),
                    TextEntry::make('effective_from')->date(),
                    TextEntry::make('effective_to')->date()->placeholder('Open'),
                    TextEntry::make('referral_source')->placeholder('—'),
                    TextEntry::make('reference')->placeholder('—'),
                    TextEntry::make('notes')->columnSpanFull()->placeholder('—'),
                ]),
        ]);
    }
}
