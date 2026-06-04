<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Filament\Resources\CustomerGroups\CustomerGroupResource;
use App\Filament\Resources\Locations\LocationResource;
use App\Models\Customer;
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
                            Grid::make(2)->schema([
                                TextEntry::make('customer_number')->label('Customer No.')->weight('bold'),
                                TextEntry::make('name')->label('Customer Name')->size('lg')->weight('bold'),
                                TextEntry::make('group_link')
                                    ->label('Customer Group')
                                    ->state(function (Customer $record): string {
                                        if (! $record->group) {
                                            return 'No group assigned';
                                        }

                                        return "{$record->group->code} - {$record->group->name}";
                                    })
                                    ->url(fn (Customer $record): ?string => $record->group
                                        ? CustomerGroupResource::getUrl('view', ['record' => $record->group])
                                        : null)
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('contact.name')
                                    ->label('Contact')
                                    ->placeholder('Auto-created from customer details'),
                            ]),
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
                            TextEntry::make('generalBusinessPostingGroup.description')
                                ->label('Gen. Bus. Posting Group')
                                ->placeholder('—'),
                            TextEntry::make('customerPostingGroup.description')
                                ->label('Customer Posting Group')
                                ->placeholder('—'),
                            TextEntry::make('vat_bus_posting_group')->label('VAT Bus. Posting Group'),
                            TextEntry::make('payment_terms_code')->label('Payment Terms'),
                        ])->columnSpan(2)->columns(2),

                    Section::make('Status & Location')
                        ->schema([
                            IconEntry::make('blocked')
                                ->boolean()
                                ->label('Blocked'),
                            TextEntry::make('blocked_reason')
                                ->badge()
                                ->visible(fn ($record) => $record->blocked)
                                ->color('danger'),
                            TextEntry::make('location_link')
                                ->label('Preferred Location')
                                ->state(function (Customer $record): string {
                                    if (! $record->location) {
                                        return 'Unassigned';
                                    }

                                    return "{$record->location->code} - {$record->location->name}";
                                })
                                ->url(fn (Customer $record): ?string => $record->location
                                    ? LocationResource::getUrl('view', ['record' => $record->location])
                                    : null),
                        ])->columnSpan(1),
                ]),
            ]);
    }
}
