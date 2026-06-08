<?php

namespace App\Filament\Resources\PettyCashVouchers\Schemas;

use App\Enums\PettyCashVoucherStatus;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class PettyCashVoucherInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Voucher Header')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('voucher_number')
                                    ->label('Voucher No.')
                                    ->badge()
                                    ->color('primary')
                                    ->copyable()
                                    ->copyMessage('Copied!')
                                    ->weight('bold'),

                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn ($state) => $state instanceof PettyCashVoucherStatus ? $state->color() : 'gray'),

                                TextEntry::make('date')
                                    ->label('Voucher Date')
                                    ->date('d/m/Y')
                                    ->icon('heroicon-o-calendar'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('fund.name')
                                    ->label('Petty Cash Fund'),

                                TextEntry::make('fund.current_balance')
                                    ->label('Fund Balance at View')
                                    ->formatStateUsing(fn ($record) => Number::currency($record->fund?->current_balance ?? 0, $record->fund?->currency ?? 'NGN'))
                                    ->color('success')
                                    ->icon('heroicon-o-banknotes'),

                                TextEntry::make('payee_name')
                                    ->label('Payee'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('payee_description')
                                    ->label('Payee Description')
                                    ->placeholder('-')
                                    ->icon('heroicon-o-information-circle'),

                                TextEntry::make('purpose')
                                    ->label('Purpose')
                                    ->placeholder('-')
                                    ->columnSpan(1),
                            ]),
                    ])->columns(1),

                Section::make('Voucher Lines')
                    ->icon('heroicon-o-list-bullet')
                    ->schema([
                        RepeatableEntry::make('lines')
//                            ->relationship('lines')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('expenseAccount.account_number')
                                            ->label('Account Code')
                                            ->weight('bold')
                                            ->badge()
                                            ->color('gray'),

                                        TextEntry::make('description')
                                            ->label('Description')
                                            ->columnSpan(2),

                                        TextEntry::make('amount')
                                            ->label('Amount')
                                            ->formatStateUsing(fn ($record) => Number::currency($record->amount, $record->voucher?->fund?->currency ?? 'NGN'))
                                            ->alignEnd()
                                            ->weight('bold'),
                                    ]),
                            ])
                            ->contained(false), // Makes it look cleaner without nested borders
                    ]),

                Section::make('Totals')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('total_amount')
                                    ->label('Total Amount')
                                    ->formatStateUsing(fn ($record) => Number::currency($record->total_amount, $record->fund?->currency ?? 'NGN'))
                                    ->size('lg')
                                    ->weight('bold')
                                    ->color('primary'),
                            ]),
                    ]),

                Section::make('Approval & Posting')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('requestedBy.name')
                                    ->label('Requested By')
                                    ->icon('heroicon-o-user')
                                    ->placeholder('-'),

                                TextEntry::make('approvedBy.name')
                                    ->label('Approved By')
                                    ->icon('heroicon-o-check-circle')
                                    ->placeholder('-'),

                                TextEntry::make('postedBy.name')
                                    ->label('Posted By')
                                    ->icon('heroicon-o-arrow-up-on-square')
                                    ->placeholder('-'),

                                TextEntry::make('posted_at')
                                    ->label('Posted At')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('-'),
                            ]),
                        TextEntry::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->placeholder('-')
                            ->color('danger')
                            ->icon('heroicon-o-x-circle')
                            ->visible(fn ($record) => $record->status === PettyCashVoucherStatus::REJECTED)
                            ->columnSpanFull(),
                    ]),

                Section::make('Notes & Audit')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('notes')
                            ->placeholder('No notes provided.')
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('-'),

                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('-'),
                            ]),
                    ]),
            ]);
    }
}
