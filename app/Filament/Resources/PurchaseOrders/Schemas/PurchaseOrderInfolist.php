<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseOrderType;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Header Information')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('order_number')
                            ->label('Order Number')
                            ->weight('bold'),

                        // FIXED: Handle both string and enum instances
                        TextEntry::make('order_type')
                            ->label('Type')
                            ->formatStateUsing(function ($state): string {
                                $enum = $state instanceof PurchaseOrderType
                                    ? $state
                                    : PurchaseOrderType::tryFrom($state);

                                return $enum?->label() ?? (string) $state;
                            })
                            ->color(function ($state): string {
                                $enum = $state instanceof PurchaseOrderType
                                    ? $state
                                    : PurchaseOrderType::tryFrom($state);

                                return $enum?->color() ?? 'gray';
                            })
                            ->icon(function ($state): ?string {
                                $enum = $state instanceof PurchaseOrderType
                                    ? $state
                                    : PurchaseOrderType::tryFrom($state);

                                return $enum?->icon();
                            }),

                        // FIXED: Same pattern for status
                        TextEntry::make('status')
                            ->label('Status')
                            ->formatStateUsing(function ($state): string {
                                $enum = $state instanceof PurchaseOrderStatus
                                    ? $state
                                    : PurchaseOrderStatus::tryFrom($state);

                                return $enum?->label() ?? (string) $state;
                            })
                            ->color(function ($state): string {
                                $enum = $state instanceof PurchaseOrderStatus
                                    ? $state
                                    : PurchaseOrderStatus::tryFrom($state);

                                return $enum?->color() ?? 'gray';
                            })
                            ->icon(function ($state): ?string {
                                $enum = $state instanceof PurchaseOrderStatus
                                    ? $state
                                    : PurchaseOrderStatus::tryFrom($state);

                                return $enum?->icon();
                            }),
                    ]),

                Section::make('Parties & Location')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('vendor.vendor_name')
                            ->label('Vendor')
                            ->helperText(fn ($record): string => $record->payment_terms ?? 'No payment terms set'),

                        TextEntry::make('location.location_name')
                            ->label('Ship To Location')
                            ->badge()
                            ->color('gray'),
                    ]),

                Section::make('Important Dates')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('order_date')
                            ->label('Order Date')
                            ->date(),

                        TextEntry::make('due_date')
                            ->label('Due Date')
                            ->date()
                            ->placeholder('-'),

                        TextEntry::make('delivery_date')
                            ->label('Expected Delivery')
                            ->date()
                            ->placeholder('-'),

                        TextEntry::make('posting_date')
                            ->label('Posting Date')
                            ->date()
                            ->placeholder('-'),
                    ]),

                Section::make('Financial Breakdown')
                    ->description('Detailed cost breakdown.')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('total_amount')
                            ->label('Total (Excl. VAT)')
                            ->money('USD'),

                        TextEntry::make('total_vat')
                            ->label('Total VAT')
                            ->money('USD'),

                        TextEntry::make('grand_total')
                            ->label('Grand Total')
                            ->money('USD')
                            ->weight('bold')
                            ->size('text-lg'),
                    ]),

                Section::make('Approval Status')
                    ->schema([
                        TextEntry::make('approver.name')
                            ->label('Approved By')
                            ->placeholder('-'),

                        TextEntry::make('approved_at')
                            ->label('Approved At')
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->collapsible()
                    ->visible(fn ($record) => $record && $record->status !== PurchaseOrderStatus::PENDING),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('comment')
                            ->label('Comments / Notes')
                            ->columnSpanFull()
                            ->placeholder('No comments provided.'),
                    ])
                    ->collapsible(),

                Section::make('Audit Trail')
                    ->schema([
                        TextEntry::make('creator.name')
                            ->label('Created By'),

                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),
                    ])
                    ->collapsible(),
            ]);
    }
}
