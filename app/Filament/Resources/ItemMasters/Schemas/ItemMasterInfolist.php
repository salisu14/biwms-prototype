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
                        // FIXED: M2M categories with primary indicator
                        TextEntry::make('categories')
                            ->label('Categories')
                            ->formatStateUsing(function ($record) {
                                return $record->categories
                                    ->map(fn ($cat) => $cat->pivot->is_primary
                                        ? "{$cat->category_name} ★"
                                        : $cat->category_name)
                                    ->join(', ');
                            }),

                        // FIXED: Primary category only
                        TextEntry::make('primaryCategory.category_name')
                            ->label('Primary Category')
                            ->placeholder('-'),
                    ]),

                Section::make('Unit of Measures')
                    ->columns(2)
                    ->schema([
                        // FIXED: Use model method to get base UOM
                        TextEntry::make('base_uom')
                            ->label('Base UOM')
                            ->state(fn ($record) => $record->getDefaultUom(UomType::BASE)?->uom_code ?? '-'),

                        // Show all assigned UOMs
                        TextEntry::make('uom_assignments')
                            ->label('All UOMs')
                            ->state(fn ($record) => $record->uomAssignments
                                ->map(fn ($ua) => "{$ua->uom->uom_code} ({$ua->uom_type->label()})")
                                ->join(', '))
                            ->placeholder('-'),
                    ]),

                Section::make('Pricing & Costing')
                    ->columns(2)
                    ->schema([
                        // FIXED: Use correct accessor
                        TextEntry::make('current_standard_cost')
                            ->label('Current Standard Cost')
                            ->money('USD'),

                        TextEntry::make('reference_cost')
                            ->label('Reference Cost')
                            ->money('USD'),

                        TextEntry::make('reference_price')
                            ->label('Reference Price')
                            ->money('USD'),

                        // FIXED: Calculate from vendor items
                        TextEntry::make('preferred_vendor')
                            ->label('Preferred Vendor')
                            ->state(fn ($record) => $record->preferredVendor()?->vendor_name ?? '-'),
                    ]),

                Section::make('Posting Setup (3NF)')
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        TextEntry::make('vat.vat_code')
                            ->label('VAT Code')
                            ->placeholder('-'),

                        TextEntry::make('generalPostingSetup.description')
                            ->label('General Posting Setup')
                            ->placeholder('-'),

                        TextEntry::make('inventoryPostingSetup.description')
                            ->label('Inventory Posting Setup')
                            ->placeholder('-'),
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
