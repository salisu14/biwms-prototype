<?php

namespace App\Filament\Resources\WarehouseShipments\Schemas;

use App\Models\WarehouseShipment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WarehouseShipmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)->schema([
                    Section::make('General Information')
                        ->schema([
                            TextInput::make('document_number')
                                ->label('Shipment No.')
                                ->required()
                                ->unique(ignoreRecord: true)
                                // Lock the field if the record already exists in the database
                                ->disabled(fn (?WarehouseShipment $record) => $record !== null)
                                // Ensure the value is still sent to the database during creation
                                ->dehydrated()
                                ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                ->helperText('The code cannot be changed once the Warehouse shipment is created.'),

                            Select::make('location_id')
                                ->label('Shipping Location')
                                ->relationship('location', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),

                            TextInput::make('status')
                                ->default('OPEN')
                                ->disabled()
                                ->dehydrated(),
                        ])->columnSpan(2),

                    Section::make('Assignment')
                        ->schema([
                            Select::make('assigned_user_id')
                                ->label('Warehouse Picker/Handler')
                                ->relationship('assignedUser', 'name')
                                ->searchable()
                                ->preload(),
                        ])->columnSpan(1),
                ]),

                Section::make('Customer & Origin')
                    ->description('Details of the outbound order and target customer.')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('customer_id')
                                ->label('Customer')
                                ->relationship('customer', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                            Grid::make(3)->schema([
                                TextInput::make('source_document')
                                    ->label('Source Type')
                                    ->placeholder('e.g., SALES_ORDER')
                                    ->required(),
                                TextInput::make('source_document_number')
                                    ->label('Source Doc No.')
                                    ->required(),
                                TextInput::make('source_document_id')
                                    ->label('Internal ID')
                                    ->numeric()
                                    ->required(),
                            ]),
                        ]),
                    ]),

                Section::make('Shipping & Logistics')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('shipping_agent_code')
                                ->label('Shipping Agent')
                                ->placeholder('e.g., FEDEX, DHL'),
                            TextInput::make('shipping_agent_service_code')
                                ->label('Agent Service')
                                ->placeholder('e.g., OVERNIGHT'),
                            TextInput::make('external_document_number')
                                ->label('External Tracking/Doc No.'),
                        ]),
                        Grid::make(3)->schema([
                            DatePicker::make('shipment_date')
                                ->label('Shipment Date')
                                ->default(now())
                                ->required(),
                            DatePicker::make('planned_delivery_date')
                                ->label('Planned Delivery'),
                            DateTimePicker::make('posted_date')
                                ->label('Posted At')
                                ->disabled()
                                ->placeholder('System generated on posting'),
                        ]),
                    ]),
            ]);
    }
}
