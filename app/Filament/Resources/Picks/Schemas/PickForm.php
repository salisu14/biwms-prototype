<?php

declare(strict_types=1);

namespace App\Filament\Resources\Picks\Schemas;

use App\Enums\WarehouseDocumentStatus;
use App\Models\WarehousePick;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class PickForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Warehouse Pick')
                    ->tabs([
                        Tabs\Tab::make('General')
                            ->icon('heroicon-m-clipboard-document-list')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('no')
                                        ->label('Pick No.')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->disabled(fn (?WarehousePick $record) => $record !== null)
                                        ->dehydrated()
                                        ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                        ->helperText('The pick number cannot be changed once created.'),

                                    Select::make('status')
                                        ->options(WarehouseDocumentStatus::class)
                                        ->default(WarehouseDocumentStatus::OPEN)
                                        ->required()
                                        ->native(false),

                                    DatePicker::make('due_date')
                                        ->label('Due Date'),
                                ]),

                                Grid::make(2)->schema([
                                    Select::make('location_id')
                                        ->relationship('location', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->required(),

                                    Select::make('assigned_user_id')
                                        ->label('Assigned To')
                                        ->relationship('assignedUser', 'name')
                                        ->searchable()
                                        ->preload(),
                                ]),
                            ]),

                        Tabs\Tab::make('Source Reference')
                            ->icon('heroicon-m-link')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('source_document')
                                        ->label('Source Type')
                                        ->placeholder('e.g., sales_order, production_order'),

                                    TextInput::make('source_no')
                                        ->label('Source Doc No.'),

                                    TextInput::make('source_id')
                                        ->label('Source Record ID')
                                        ->numeric(),
                                ]),

                                Select::make('warehouse_shipment_id')
                                    ->label('Linked Warehouse Shipment')
                                    ->relationship('warehouseShipment', 'document_number')
                                    ->searchable()
                                    ->preload(),
                            ]),

                        Tabs\Tab::make('Execution')
                            ->icon('heroicon-m-play-circle')
                            ->schema([
                                Grid::make(2)->schema([
                                    DateTimePicker::make('started_at')
                                        ->label('Work Started'),

                                    DateTimePicker::make('completed_at')
                                        ->label('Work Completed'),
                                ]),

                                Textarea::make('remarks')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}
