<?php

namespace App\Filament\Resources\WarehousePutaways\Schemas;

use App\Models\WarehousePutaway;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WarehousePutawayForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)->schema([
                    Section::make('General Information')
                        ->schema([
                            TextInput::make('no')
                                ->label('Put-away No.')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->disabled(fn (?WarehousePutaway $record) => $record !== null)
                                ->dehydrated()
                                ->extraInputAttributes(['style' => 'text-transform: uppercase']),

                            Select::make('location_id')
                                ->relationship('location', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),

                            Select::make('warehouse_receipt_id')
                                ->label('Source Receipt')
                                ->relationship('warehouseReceipt', 'document_number')
                                ->searchable()
                                ->preload()
                                ->helperText('The posted receipt this put-away is derived from.'),
                        ])->columnSpan(2),

                    Section::make('Status & Execution')
                        ->schema([
                            TextInput::make('status')
                                ->required()
                                ->default('Open')
                                ->disabled()
                                ->dehydrated(),

                            Select::make('assigned_user_id')
                                ->label('Assigned User')
                                ->relationship('assignedUser', 'name')
                                ->searchable()
                                ->preload(),

                            Select::make('sorting_method')
                                ->options([
                                    'Item' => 'Item',
                                    'Bin Ranking' => 'Bin Ranking',
                                    'Document' => 'Document',
                                    'Due Date' => 'Due Date',
                                ])
                                ->native(false),
                        ])->columnSpan(1),
                ]),
            ]);
    }
}
