<?php

declare(strict_types=1);

namespace App\Filament\Resources\Picks\Schemas;

use App\Enums\WarehouseDocumentStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PickInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pick Identification')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('no')
                                ->label('Pick No.')
                                ->weight('bold')
                                ->copyable(),

                            TextEntry::make('status')
                                ->badge()
                                ->color(fn (WarehouseDocumentStatus $state): string => match ($state) {
                                    WarehouseDocumentStatus::OPEN => 'gray',
                                    WarehouseDocumentStatus::RELEASED => 'info',
                                    WarehouseDocumentStatus::IN_PROGRESS => 'warning',
                                    WarehouseDocumentStatus::COMPLETED => 'success',
                                    WarehouseDocumentStatus::CANCELLED => 'danger',
                                }),

                            TextEntry::make('due_date')
                                ->label('Due Date')
                                ->date()
                                ->placeholder('No due date'),
                        ]),
                    ]),

                Section::make('Location & Assignment')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('location.name')
                                ->label('Location')
                                ->icon('heroicon-m-map-pin')
                                ->color('primary'),

                            TextEntry::make('assignedUser.name')
                                ->label('Assigned To')
                                ->icon('heroicon-m-user')
                                ->placeholder('Unassigned'),
                        ]),
                    ]),

                Section::make('Source Document')
                    ->description('Origin document that triggered this pick.')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('source_document')->label('Source Type')->placeholder('-'),
                            TextEntry::make('source_no')->label('Source No.')->placeholder('-'),
                            TextEntry::make('warehouseShipment.document_number')
                                ->label('Linked Shipment')
                                ->placeholder('None'),
                        ]),
                    ]),

                Section::make('Timeline')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('started_at')->dateTime()->placeholder('Not started'),
                            TextEntry::make('completed_at')->dateTime()->placeholder('Incomplete'),
                            TextEntry::make('completed_at')
                                ->label('Duration')
                                ->state(fn ($record) => $record->started_at && $record->completed_at
                                    ? $record->started_at->diffForHumans($record->completed_at, true)
                                    : '-')
                                ->icon('heroicon-m-clock'),
                        ]),
                    ]),

                Section::make('Remarks')
                    ->schema([
                        TextEntry::make('remarks')
                            ->placeholder('No remarks recorded.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
