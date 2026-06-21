<?php

namespace App\Filament\Resources\DiscountRules\Schemas;

use App\Filament\Resources\CustomerGroups\CustomerGroupResource;
use App\Filament\Resources\Items\ItemResource;
use App\Models\DiscountRule;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DiscountRuleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Scope')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('item_label')
                            ->label('Item')
                            ->state(function (DiscountRule $record): string {
                                return $record->item
                                    ? "{$record->item->item_code} - {$record->item->description}"
                                    : '—';
                            })
                            ->url(fn (DiscountRule $record): ?string => $record->item
                                ? ItemResource::getUrl('view', ['record' => $record->item])
                                : null),
                        TextEntry::make('customer_group_label')
                            ->label('Customer Group')
                            ->state(function (DiscountRule $record): string {
                                return $record->customerGroup
                                    ? "{$record->customerGroup->code} - {$record->customerGroup->name}"
                                    : 'All Groups';
                            })
                            ->url(fn (DiscountRule $record): ?string => $record->customerGroup
                                ? CustomerGroupResource::getUrl('view', ['record' => $record->customerGroup])
                                : null),
                    ]),

                Section::make('Discount')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('discount_percent')
                            ->label('Discount %')
                            ->badge()
                            ->color('success')
                            ->suffix('%'),
                        TextEntry::make('validity')
                            ->label('Validity')
                            ->state(function (DiscountRule $record): string {
                                $start = $record->start_date?->format('d/m/Y') ?? '—';
                                $end = $record->end_date?->format('d/m/Y') ?? 'Open';

                                return "{$start} - {$end}";
                            }),
                    ]),

                Section::make('Status')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (DiscountRule $record): string => $record->end_date && $record->end_date->isPast()
                                ? 'danger'
                                : ($record->start_date && $record->start_date->isFuture() ? 'info' : 'success'))
                            ->state(function (DiscountRule $record): string {
                                if ($record->end_date && $record->end_date->isPast()) {
                                    return 'Expired';
                                }

                                if ($record->start_date && $record->start_date->isFuture()) {
                                    return 'Scheduled';
                                }

                                return 'Active';
                            }),
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->label('Created At'),
                    ]),
            ]);
    }
}
