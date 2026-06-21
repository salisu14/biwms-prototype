<?php

namespace App\Filament\Resources\WarehouseJournalBatches\Schemas;

use App\Models\ReasonCode;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WarehouseJournalBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Batch Details')
                    ->columns(2)
                    ->schema([
                        Select::make('template_id')
                            ->label('Journal Template')
                            ->relationship('template', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false),

                        TextInput::make('name')
                            ->label('Batch Name')
                            ->required()
                            ->maxLength(50),

                        Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull(),

                        Select::make('assigned_user_id')
                            ->label('Assigned User')
                            ->relationship('assignedUser', 'name')
                            ->searchable()
                            ->preload(),

                        Select::make('journal_type')
                            ->label('Override Journal Type')
                            ->options([
                                'pick' => 'Pick',
                                'put_away' => 'Put-Away',
                                'movement' => 'Movement',
                                'physical_inventory' => 'Physical Inventory',
                                'adjustment' => 'Adjustment',
                            ])
                            ->native(false)
                            ->helperText('Overrides the template type for this batch only.'),
                    ]),

                Section::make('Location & Filtering')
                    ->columns(2)
                    ->schema([
                        Select::make('location_id')
                            ->label('Location')
                            ->relationship('location', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Select::make('zone_id')
                            ->label('Zone Filter')
                            ->relationship('zone', 'code')
                            ->searchable()
                            ->preload()
                            ->helperText('Restrict lines to this zone.'),

                        Select::make('reason_code')
                            ->label('Default Reason Code')
                            ->options(fn () => ReasonCode::query()
                                ->where('blocked', false)
                                ->orderBy('code')
                                ->pluck('description', 'code'))
                            ->searchable()
                            ->preload(),

                        Toggle::make('copy_from_warehouse_activity')
                            ->label('Copy from Warehouse Activity')
                            ->default(true)
                            ->inline(false)
                            ->helperText('Pre-populate lines from pick/put-away worksheets.'),
                    ]),
            ]);
    }
}
