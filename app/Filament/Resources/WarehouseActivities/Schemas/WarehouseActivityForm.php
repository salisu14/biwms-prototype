<?php

namespace App\Filament\Resources\WarehouseActivities\Schemas;

use App\Enums\WarehouseActivityType;
use App\Enums\WarehouseDocumentStatus;
use App\Models\WarehouseActivity;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class WarehouseActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Warehouse Activity')
                    ->tabs([
                        Tabs\Tab::make('General')
                            ->icon('heroicon-m-clipboard-document-list')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('no')
                                        ->label('Activity No.')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        // Lock the field if the record already exists in the database
                                        ->disabled(fn (?WarehouseActivity $record) => $record !== null)
                                        // Ensure the value is still sent to the database during creation
                                        ->dehydrated()
                                        ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                        ->helperText('The code cannot be changed once the Warehouse activity is created.'),

                                    Select::make('activity_type')
                                        ->options(WarehouseActivityType::class)
                                        ->required()
                                        ->native(false),

                                    Select::make('status')
                                        ->options(WarehouseDocumentStatus::class)
                                        ->default(WarehouseDocumentStatus::OPEN)
                                        ->required()
                                        ->native(false),
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
                                        ->placeholder('e.g., PURCHASE_RECEIPT'),
                                    TextInput::make('source_no')
                                        ->label('Source Doc No.'),
                                    TextInput::make('source_id')
                                        ->label('Source Record ID')
                                        ->numeric(),
                                ]),
                                TextInput::make('source_line_no')
                                    ->label('Source Line No.')
                                    ->numeric(),
                            ]),

                        Tabs\Tab::make('Execution & Notes')
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
