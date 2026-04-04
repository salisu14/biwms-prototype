<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)->schema([
                    Section::make('Customer Profile')
                        ->schema([
                            TextEntry::make('customer_number')->label('Account #')->weight('bold'),
                            TextEntry::make('name')->size('lg')->weight('bold'),
                            TextEntry::make('email')->icon('heroicon-m-envelope')->copyable(),
                            TextEntry::make('phone')->icon('heroicon-m-phone'),
                            TextEntry::make('address')->columnSpanFull(),
                        ])->columnSpan(2)->columns(2),

                    Section::make('Financial Status')
                        ->schema([
                            TextEntry::make('balance')
                                ->money()
                                ->weight('bold')
                                ->color(fn ($record) => $record->isOverCreditLimit() ? 'danger' : 'success'),
                            TextEntry::make('open_balance')
                                ->label('Open Balance')
                                ->money(),
                            TextEntry::make('overdue_balance')
                                ->label('Overdue')
                                ->money()
                                ->color('danger'),
                            TextEntry::make('available_credit')
                                ->label('Available Credit')
                                ->money()
                                ->placeholder('Unlimited'),
                        ])->columnSpan(1),
                ]),

                Grid::make(3)->schema([
                    Section::make('Account Setup')
                        ->schema([
                            TextEntry::make('generalBusinessPostingGroup.id')->label('Gen. Bus. Posting'),
                            TextEntry::make('customerPostingGroup.id')->label('Customer Posting'),
                            TextEntry::make('vat_bus_posting_group')->label('VAT Group'),
                            TextEntry::make('payment_terms_code')->label('Payment Terms'),
                        ])->columnSpan(2)->columns(2),

                    Section::make('Status Details')
                        ->schema([
                            IconEntry::make('blocked')
                                ->boolean()
                                ->label('Blocked'),
                            TextEntry::make('blocked_reason')
                                ->badge()
                                ->visible(fn ($record) => $record->blocked)
                                ->color('danger'),
                            TextEntry::make('location.name')->label('Preferred Location'),
                        ])->columnSpan(1),
                ]),
            ]);
    }
}
