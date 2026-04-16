<?php

namespace App\Filament\Resources\FixedAssets\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FixedAssetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('fa_no')->label('Asset No.')->weight('bold'),
                        TextEntry::make('description')->columnSpan(2),
                        TextEntry::make('fa_type')->badge(),
                        TextEntry::make('faClass.name')->label('Class'),
                        TextEntry::make('status')->badge(),
                        IconEntry::make('blocked')->boolean(),
                    ]),

                Section::make('Financial Status')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('acquisition_cost')->money(),
                        TextEntry::make('accumulated_depreciation')->money(),
                        TextEntry::make('book_value')->label('Book Value')->money(),
                        TextEntry::make('depreciation_method')->badge(),
                        TextEntry::make('useful_life_years')->suffix(' Years'),
                        TextEntry::make('salvage_value')->money(),
                    ]),

                Section::make('Acquisition & Dates')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('acquisition_date')->date(),
                        TextEntry::make('depreciation_starting_date')->date(),
                        TextEntry::make('depreciation_ending_date')->date(),
                        TextEntry::make('vendor.name')->label('Vendor'),
                        TextEntry::make('acquisition_invoice_no'),
                    ]),

                Section::make('Physical Information')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('serial_no'),
                        TextEntry::make('barcode'),
                        TextEntry::make('location.name')->label('Location'),
                        TextEntry::make('fa_location_code'),
                        TextEntry::make('responsibleEmployee.name'),
                    ]),
            ]);
    }
}
