<?php

namespace App\Filament\Resources\ItemMasters\Schemas;

use App\Enums\UomType;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemMasterInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('item_code')
                            ->icon('heroicon-m-identification'),

                        TextEntry::make('description')
                            ->columnSpanFull(),

                        TextEntry::make('item_type')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state?->label() ?? '-'),

                        TextEntry::make('inventory_method')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state?->label() ?? '-')
                            ->visible(fn ($record) => $record->item_type?->requiresInventoryTracking()),

                        IconEntry::make('is_active')
                            ->boolean()
                            ->label('Active'),
                    ]),

                Section::make('Classification')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('category_list')
                            ->label('Categories')
                            ->state(fn ($record) => $record->categories?->count()
                                ? $record->categories
                                    ->map(fn ($cat) => $cat->pivot?->is_primary
                                        ? "{$cat->category_name} ★"
                                        : $cat->category_name)
                                    ->join(', ')
                                : '-'
                            ),

                        TextEntry::make('primary_category_name')
                            ->label('Primary Category')
                            ->state(fn ($record) => $record->getPrimaryCategory()?->category_name ?? '-'),
                    ]),

                Section::make('Unit of Measures')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('base_uom_code')
                            ->label('Base UOM')
                            ->state(fn ($record) => $record->getDefaultUom(UomType::BASE)?->uom_code ?? '-'),

                        TextEntry::make('uom_summary')
                            ->label('All UOMs')
                            ->state(fn ($record) => $record->uomAssignments?->count()
                                ? $record->uomAssignments
                                    ->map(function ($ua) {
                                        $uomCode = $ua->uom?->uom_code ?? 'N/A';

                                        // FIX: Handle if uom_type is a string (from DB) or an Enum instance
                                        $type = $ua->uom_type;
                                        if (is_string($type)) {
                                            $typeLabel = UomType::tryFrom($type)?->label() ?? $type;
                                        } elseif ($type instanceof UomType) {
                                            $typeLabel = $type->label();
                                        } else {
                                            $typeLabel = '-';
                                        }

                                        return "{$uomCode} ({$typeLabel})";
                                    })
                                    ->join(', ')
                                : '-'
                            )
                            ->placeholder('-'),
                    ]),

                Section::make('Pricing & Costing')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('current_standard_cost')
                            ->label('Current Standard Cost')
                            ->money('USD'),

                        TextEntry::make('reference_cost')
                            ->label('Reference Cost')
                            ->money('USD'),

                        TextEntry::make('reference_price')
                            ->label('Reference Price')
                            ->money('USD'),

                        TextEntry::make('preferred_vendor_name')
                            ->label('Preferred Vendor')
                            ->state(fn ($record) => $record->preferredVendor?->vendor_name ?? '-'),
                    ]),

                Section::make('Posting Setup (3NF)')
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        TextEntry::make('vat_code')
                            ->label('VAT Code')
                            ->state(fn ($record) => $record->vat?->vat_code ?? '-'),

                        TextEntry::make('general_posting_setup_desc')
                            ->label('General Posting Setup')
                            ->state(fn ($record) => $record->generalPostingSetup?->description ?? '-'),

                        TextEntry::make('inventory_posting_setup_desc')
                            ->label('Inventory Posting Setup')
                            ->state(fn ($record) => $record->inventoryPostingSetup?->description ?? '-'),
                    ]),

                Section::make('Additional')
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('shelf_life_days')
                            ->numeric()
                            ->placeholder('-')
                            ->suffix(' days'),

                        TextEntry::make('created_at')
                            ->dateTime()
                            ->placeholder('-'),

                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
