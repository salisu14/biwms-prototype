<?php

namespace App\Filament\Resources\WarehouseReceipts\Schemas;

use App\Enums\WarehouseReceiptStatus;
use App\Filament\Traits\HasSystemGeneratedField;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WarehouseReceiptForm
{
    use HasSystemGeneratedField;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)->schema([
                    Section::make('General Information')
                        ->schema([
                            static::makeSystemGeneratedTextInput(
                                'document_number',
                                'Receipt No.',
                                'Generated automatically from the warehouse receipt number series and cannot be changed.'
                            ),

                            Select::make('location_id')
                                ->label('Receiving Location')
                                ->relationship('location', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                            // UPDATED: Now uses the WarehouseReceiptStatus enum
                            Select::make('status')
                                ->label('Document Status')
                                ->options(WarehouseReceiptStatus::class)
                                ->default(WarehouseReceiptStatus::OPEN)
                                ->required()
                                ->native(false),
                        ])->columnSpan(2),

                    Section::make('Team Assignment')
                        ->schema([
                            Select::make('assigned_user_id')
                                ->label('Assigned To')
                                ->relationship('assignedUser', 'name') // Assuming assignedUser relationship exists
                                ->searchable()
                                ->preload(),
                        ])->columnSpan(1),
                ]),

                Section::make('Source Reference')
                    ->description('Tracking links to the originating document.')
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('source_document')
                                ->label('Source Document Type')
                                ->options([
                                    'PURCHASE_ORDER' => 'Purchase Order',
                                    'SALES_RETURN_ORDER' => 'Sales Return Order',
                                    'INBOUND_TRANSFER' => 'Inbound Transfer',
                                ])
                                ->required()
                                ->native(false)
                                ->helperText('Select the originating document type.'),

                            TextInput::make('source_document_number')
                                ->label('Source Doc No.')
                                ->required(),
                            TextInput::make('source_document_id')
                                ->label('Internal ID')
                                ->numeric()
                                ->required(),
                        ]),
                        Select::make('vendor_id')
                            ->label('Source Vendor')
                            ->relationship('vendor', 'vendor_name')
                            ->searchable()
                            ->preload(),
                    ]),

                Section::make('Logistics & Dates')
                    ->schema([
                        Grid::make(3)->schema([
                            DatePicker::make('receipt_date')
                                ->label('Receipt Date')
                                ->default(now())
                                ->required(),
                            DatePicker::make('expected_receipt_date')
                                ->label('Expected On'),
                            DateTimePicker::make('posted_date')
                                ->label('Posted At')
                                ->disabled()
                                ->placeholder('System generated on posting'),
                        ]),
                    ]),
            ]);
    }
}
