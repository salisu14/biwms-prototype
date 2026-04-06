<?php

namespace App\Filament\Resources\SalesQuotes\Schemas;

use App\Enums\QuoteStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class SalesQuoteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Group::make([
                                    TextEntry::make('quote_no')
                                        ->label('Quote Number')
                                        ->weight(FontWeight::Bold)
                                        ->copyable()
                                        ->icon('heroicon-m-hashtag')
                                        ->color('primary'),

                                    TextEntry::make('customer.name')
                                        ->label('Customer')
                                        ->weight(FontWeight::SemiBold)
                                        ->icon('heroicon-m-user'),
                                ]),

                                Group::make([
                                    TextEntry::make('quote_date')
                                        ->label('Issue Date')
                                        ->date()
                                        ->icon('heroicon-m-calendar-days'),

                                    TextEntry::make('valid_until')
                                        ->label('Expiry Date')
                                        ->date()
                                        ->placeholder('No expiry set')
                                        ->icon('heroicon-m-clock')
                                        ->color(fn ($state) => now()->gt($state) ? 'danger' : 'gray'),
                                ]),

                                Group::make([
                                    TextEntry::make('status')
                                        ->badge()
                                        ->formatStateUsing(fn (QuoteStatus $state) => ucfirst($state->value)) // show label
                                        ->color(fn (QuoteStatus $state): string => match ($state->value) {
                                            'draft' => 'gray',
                                            'sent' => 'info',
                                            'accepted' => 'success',
                                            'declined' => 'danger',
                                            default => 'gray',
                                        }),

                                    TextEntry::make('total_amount')
                                        ->label('Grand Total')
                                        ->money('USD')
                                        ->weight(FontWeight::Black)
                                        ->size(TextSize::Large)
                                        ->color('primary'),
                                ]),
                            ]),
                    ]),

                Grid::make(2)
                    ->schema([
                        Section::make('Approval & Verification')
                            ->description('Internal validation details')
                            ->columnSpan(1)
                            ->schema([
                                TextEntry::make('approval_status')
                                    ->badge()
                                    // FIX: Accept string $state, not QuoteStatus
                                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        default => 'gray',
                                    }),

                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('approved_by_user.name')
                                            ->label('Reviewer')
                                            ->placeholder('Awaiting Review')
                                            ->icon('heroicon-m-shield-check'),

                                        TextEntry::make('approved_at')
                                            ->label('Reviewed On')
                                            ->dateTime()
                                            ->placeholder('-'),
                                    ]),
                            ]),

                        Section::make('System Metadata')
                            ->description('Record traceability')
                            ->columnSpan(1)
                            ->collapsed()
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label('Created')
                                            ->dateTime()
                                            ->color('gray'),

                                        TextEntry::make('updated_at')
                                            ->label('Last Modified')
                                            ->dateTime()
                                            ->color('gray'),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
