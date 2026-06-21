<?php

namespace App\Filament\Resources\ItemCharges\Schemas;

use App\Filament\Resources\GeneralProductPostingGroups\GeneralProductPostingGroupResource;
use App\Filament\Resources\VatProductPostingGroups\VatProductPostingGroupResource;
use App\Models\ItemCharge;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemChargeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identification')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('number')
                            ->label('Charge No.')
                            ->weight('bold'),
                        TextEntry::make('description')
                            ->label('Description')
                            ->weight('bold'),
                        TextEntry::make('description_2')
                            ->label('Description 2')
                            ->placeholder('—'),
                        TextEntry::make('search_description')
                            ->label('Search Description')
                            ->placeholder('—'),
                    ]),

                Section::make('Posting Setup')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('general_posting_group')
                            ->label('Gen. Prod. Posting Group')
                            ->state(function (ItemCharge $record): string {
                                return $record->generalPostingGroup
                                    ? "{$record->generalPostingGroup->code} - {$record->generalPostingGroup->name}"
                                    : '—';
                            })
                            ->url(fn (ItemCharge $record): ?string => $record->generalPostingGroup
                                ? GeneralProductPostingGroupResource::getUrl('view', ['record' => $record->generalPostingGroup])
                                : null),
                        TextEntry::make('vat_posting_group')
                            ->label('VAT Prod. Posting Group')
                            ->state(function (ItemCharge $record): string {
                                return $record->vatPostingGroup
                                    ? "{$record->vatPostingGroup->code} - {$record->vatPostingGroup->name}"
                                    : '—';
                            })
                            ->url(fn (ItemCharge $record): ?string => $record->vatPostingGroup
                                ? VatProductPostingGroupResource::getUrl('view', ['record' => $record->vatPostingGroup])
                                : null),
                    ]),

                Section::make('Usage')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('purchase_order_lines_count')
                            ->label('Purchase Order Lines')
                            ->state(fn (ItemCharge $record): string => (string) $record->purchaseOrderLines()->count())
                            ->badge()
                            ->color('info')
                            ->suffix(' lines'),
                        TextEntry::make('purchase_receipt_lines_count')
                            ->label('Purchase Receipt Lines')
                            ->state(fn (ItemCharge $record): string => (string) $record->purchaseReceiptLines()->count())
                            ->badge()
                            ->color('success')
                            ->suffix(' lines'),
                    ]),

                Section::make('Metadata')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')->dateTime()->label('Created At'),
                        TextEntry::make('updated_at')->dateTime()->label('Updated At'),
                    ]),
            ]);
    }
}
