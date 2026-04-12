<?php

namespace App\Filament\Resources\WarehouseReceipts\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WarehouseReceiptInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Identification')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('document_number')
                                ->label('Receipt No.')
                                ->weight('bold')
                                ->copyable(),
                            TextEntry::make('status')
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'OPEN' => 'gray',
                                    'RELEASED' => 'info',
                                    'PARTIALLY_RECEIVED' => 'warning',
                                    'RECEIVED' => 'success',
                                    default => 'gray',
                                }),
                            TextEntry::make('location.name')
                                ->label('Receiving Location'),
                        ]),
                    ]),

                Section::make('Origin & Vendor')
                    ->description('Details of the source document and supply entity.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('vendor.vendor_name')
                                ->label('Vendor Name')
                                ->weight('bold'),
                            Grid::make(3)->schema([
                                TextEntry::make('source_document')
                                    ->label('Source Type'),
                                TextEntry::make('source_document_number')
                                    ->label('Source Doc No.'),
                                TextEntry::make('source_document_id')
                                    ->label('Internal ID'),
                            ]),
                        ]),
                    ]),

                Section::make('Logistics Timeline')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('receipt_date')
                                ->date(),
                            TextEntry::make('expected_receipt_date')
                                ->date()
                                ->placeholder('Not specified'),
                            TextEntry::make('posted_date')
                                ->dateTime()
                                ->placeholder('Not yet posted'),
                        ]),
                    ]),

                Section::make('System & Audit')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('assignedUser.name')
                                ->label('Assigned User')
                                ->placeholder('Unassigned'),
                            Grid::make(2)->schema([
                                TextEntry::make('created_at')
                                    ->dateTime(),
                                TextEntry::make('updated_at')
                                    ->dateTime(),
                            ]),
                        ]),
                    ]),
            ]);
    }
}
