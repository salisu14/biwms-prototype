<?php

namespace App\Filament\Resources\PricingMasters\Schemas;

use App\Models\PricingMaster;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class PricingMasterInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Scope & Applicability')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        TextEntry::make('price_list_code')->label('Code')->badge()->color('primary'),
                        TextEntry::make('description')->placeholder('-'),
                        TextEntry::make('price_list_type')
                            ->badge()
                            ->color('info')
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'ALL_CUSTOMERS' => 'All Customers',
                                'CUSTOMER' => 'Specific Customer',
                                'CUSTOMER_GROUP' => 'Customer Group',
                                'CAMPAIGN' => 'Campaign / Promo',
                                'TRANSFER' => 'Transfer',
                                default => str_replace('_', ' ', $state),
                            }),
                        TextEntry::make('customer.customer_number')
                            ->label('Customer')
                            ->formatStateUsing(fn ($state, PricingMaster $record): string => $record->customer ? "{$record->customer->customer_number} - {$record->customer->name}" : 'All Customers')
                            ->placeholder('All Customers'),
                        TextEntry::make('pricingGroup.code')
                            ->label('Pricing Group')
                            ->formatStateUsing(fn ($state, PricingMaster $record): string => $record->pricingGroup ? "{$record->pricingGroup->code} - {$record->pricingGroup->name}" : '-')
                            ->placeholder('-'),
                        TextEntry::make('item.item_code')->label('Item Code')->placeholder('-'),
                        TextEntry::make('item.description')->label('Item Description')->placeholder('-'),
                        TextEntry::make('variant_code')->label('Variant')->placeholder('-'),
                        TextEntry::make('location.name')->label('Location')->placeholder('All Locations'),
                        TextEntry::make('currency_code')->badge()->color('gray'),
                    ])->columns(3),

                Section::make('Pricing Details')
                    ->icon('heroicon-o-currency-dollar')
                    ->schema([
                        TextEntry::make('price_type')
                            ->badge()
                            ->color('warning')
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'UNIT_PRICE' => 'Fixed Unit Price',
                                'PERCENT_DISCOUNT' => 'Discount %',
                                'AMOUNT_DISCOUNT' => 'Discount Amount',
                                'COST_PLUS_PERCENT' => 'Cost + %',
                                'COST_PLUS_AMOUNT' => 'Cost + Amount',
                                'FORMULA' => 'Formula',
                                default => str_replace('_', ' ', $state),
                            }),
                        TextEntry::make('unit_price')
                            ->formatStateUsing(fn ($state, PricingMaster $record): string => Number::currency((float) $state, $record->currency_code))
                            ->visible(fn ($record) => $record->price_type === 'UNIT_PRICE'),
                        TextEntry::make('discount_percent')->suffix('%')->visible(fn ($record) => $record->price_type === 'PERCENT_DISCOUNT'),
                        TextEntry::make('discount_amount')
                            ->formatStateUsing(fn ($state, PricingMaster $record): string => Number::currency((float) $state, $record->currency_code))
                            ->visible(fn ($record) => $record->price_type === 'AMOUNT_DISCOUNT'),
                        TextEntry::make('cost_plus_percent')->suffix('%')->visible(fn ($record) => in_array($record->price_type, ['COST_PLUS_PERCENT', 'COST_PLUS_AMOUNT'])),
                        IconEntry::make('allow_quantity_breaks')->boolean()->label('Qty Breaks Allowed?'),
                        TextEntry::make('minimum_quantity')->numeric()->label('Min Qty'),
                        TextEntry::make('maximum_quantity')->numeric()->label('Max Qty')->placeholder('Unlimited'),
                        TextEntry::make('minimum_order_amount')
                            ->formatStateUsing(fn ($state, PricingMaster $record): string => Number::currency((float) $state, $record->currency_code))
                            ->placeholder('-'),
                        TextEntry::make('minimum_order_quantity')->numeric()->placeholder('-'),
                    ])->columns(3),

                Section::make('Schedule & Effectivity')
                    ->icon('heroicon-o-calendar')
                    ->schema([
                        TextEntry::make('start_date')->date('d/m/Y'),
                        TextEntry::make('end_date')->date('d/m/Y')->placeholder('No End Date'),
                        TextEntry::make('start_time')->time('H:i')->placeholder('-'),
                        TextEntry::make('end_time')->time('H:i')->placeholder('-'),
                        TextEntry::make('applicable_days')
                            ->formatStateUsing(fn ($state) => $state ? collect($state)->map(fn ($d) => ucfirst($d))->join(', ') : 'All Days')
                            ->badge()->color('info'),
                        TextEntry::make('minimum_lead_time_days')->suffix(' days')->numeric(),
                    ])->columns(3),

                Section::make('Status & Audit')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        TextEntry::make('status')->badge()->color(fn ($state) => match ($state) {
                            'ACTIVE' => 'success',
                            'DRAFT' => 'gray',
                            'PENDING_APPROVAL' => 'warning',
                            'EXPIRED' => 'danger',
                            'CANCELLED' => 'danger',
                            default => 'gray',
                        }),
                        TextEntry::make('priority')->numeric(),
                        IconEntry::make('is_current_version')->boolean()->label('Is Current Version?'),
                        TextEntry::make('approvedByUser.name')->label('Approved By')->placeholder('-'),
                        TextEntry::make('createdByUser.name')->label('Created By')->placeholder('-'),
                        TextEntry::make('modifiedByUser.name')->label('Modified By')->placeholder('-'),
                        TextEntry::make('approved_at')->dateTime()->placeholder('-'),
                        TextEntry::make('replaces.price_list_code')->label('Replaces')->placeholder('-'),
                        TextEntry::make('replacedBy.price_list_code')->label('Replaced By')->placeholder('-'),
                        TextEntry::make('modification_reason')->placeholder('-')->columnSpanFull(),
                        TextEntry::make('created_at')->dateTime()->placeholder('-'),
                        TextEntry::make('updated_at')->dateTime()->placeholder('-'),
                        TextEntry::make('deleted_at')->dateTime()->visible(fn (PricingMaster $record): bool => $record->trashed()),
                    ])->columns(3),
            ]);
    }
}
