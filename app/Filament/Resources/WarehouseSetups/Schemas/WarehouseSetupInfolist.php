<?php

namespace App\Filament\Resources\WarehouseSetups\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WarehouseSetupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Constraints')
                    ->description('Fundamental requirements for warehouse transactions.')
                    ->columns(3)
                    ->schema([
                        IconEntry::make('location_mandatory')
                            ->label('Location Mandatory')
                            ->boolean()
                            ->color('primary'),
                        IconEntry::make('bin_mandatory')
                            ->label('Bin Mandatory')
                            ->boolean()
                            ->color('primary'),
                        TextEntry::make('updated_at')
                            ->label('Last Configuration Change')
                            ->dateTime()
                            ->color('gray'),
                    ]),

                Grid::make(2)->schema([
                    Section::make('Inbound Flow')
                        ->columnSpan(1)
                        ->icon('heroicon-o-arrow-down-on-square')
                        ->schema([
                            IconEntry::make('require_receive')
                                ->label('Require Receipt')
                                ->boolean()
                                ->color('info'),
                            IconEntry::make('require_putaway')
                                ->label('Require Put-away')
                                ->boolean()
                                ->color('info'),
                        ]),

                    Section::make('Outbound Flow')
                        ->columnSpan(1)
                        ->icon('heroicon-o-arrow-up-on-square')
                        ->schema([
                            IconEntry::make('require_shipment')
                                ->label('Require Shipment')
                                ->boolean()
                                ->color('success'),
                            IconEntry::make('require_pick')
                                ->label('Require Pick')
                                ->boolean()
                                ->color('success'),
                        ]),
                ]),

                Section::make('Advanced Warehousing & Logic')
                    ->description('Directed movements and complex bin management.')
                    ->columns(3)
                    ->schema([
                        IconEntry::make('directed_putaway_and_pick')
                            ->label('Directed Put-away & Pick')
                            ->boolean()
                            ->color('danger'),
                        IconEntry::make('pick_according_to_fefo')
                            ->label('FEFO Picking')
                            ->boolean()
                            ->color('warning'),
                        IconEntry::make('allow_breakbulk')
                            ->label('Allow Breakbulk')
                            ->boolean()
                            ->color('gray'),

                        TextEntry::make('bin_capacity_policy')
                            ->label('Bin Capacity Policy')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('default_bin_selection')
                            ->label('Default Bin Selection Method'),
                        TextEntry::make('putaway_template_nos')
                            ->label('Put-away Template')
                            ->placeholder('None Assigned'),
                    ]),

                Section::make('Numbering Series')
                    ->description('Automated document ID sequences.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('warehouse_receipt_nos')
                            ->label('Warehouse Receipt Nos.')
                            ->placeholder('Not Defined')
                            ->icon('heroicon-m-hashtag'),
                        TextEntry::make('warehouse_shipment_nos')
                            ->label('Warehouse Shipment Nos.')
                            ->placeholder('Not Defined')
                            ->icon('heroicon-m-hashtag'),
                        TextEntry::make('internal_putaway_nos')
                            ->label('Internal Put-away Nos.')
                            ->placeholder('Not Defined')
                            ->icon('heroicon-m-hashtag'),
                        TextEntry::make('internal_pick_nos')
                            ->label('Internal Pick Nos.')
                            ->placeholder('Not Defined')
                            ->icon('heroicon-m-hashtag'),
                    ]),

                Section::make('Audit Trail')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ]),
            ]);
    }
}
