<?php

namespace App\Filament\Resources\WarehouseJournalTemplates\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WarehouseJournalTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template Identity')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Template Code')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->helperText('e.g. WH-MOVE, WH-PHYS, WH-ADJ'),

                        Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull(),

                        Select::make('journal_type')
                            ->label('Journal Type')
                            ->required()
                            ->native(false)
                            ->options([
                                'pick' => 'Pick',
                                'put_away' => 'Put-Away',
                                'movement' => 'Movement',
                                'physical_inventory' => 'Physical Inventory',
                                'adjustment' => 'Adjustment',
                            ])
                            ->helperText('Determines which entry types are allowed on lines.'),

                        Select::make('number_series_id')
                            ->label('Number Series')
                            ->relationship('numberSeries', 'code')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('source_code')
                            ->label('Source Code')
                            ->maxLength(20)
                            ->helperText('e.g. WHJNL — appears on posted warehouse entries.'),
                    ]),

                Section::make('Warehouse Controls')
                    ->columns(2)
                    ->schema([
                        Toggle::make('bin_mandatory')
                            ->label('Bin Mandatory')
                            ->default(true)
                            ->inline(false)
                            ->helperText('Require a bin on every line.'),

                        Toggle::make('zone_mandatory')
                            ->label('Zone Mandatory')
                            ->default(false)
                            ->inline(false),

                        Toggle::make('item_tracking_mandatory')
                            ->label('Item Tracking Mandatory')
                            ->default(false)
                            ->inline(false)
                            ->helperText('Force Lot / Serial No. on tracked items.'),

                        Toggle::make('directed_put_away_and_pick')
                            ->label('Directed Put-Away & Pick')
                            ->default(false)
                            ->inline(false)
                            ->helperText('Enable advanced WMS bin directives.'),

                        Toggle::make('require_warehouse_activity')
                            ->label('Require Warehouse Activity')
                            ->default(false)
                            ->inline(false)
                            ->helperText('Lines must originate from a pick/put-away worksheet.'),

                        Toggle::make('require_reason_code')
                            ->label('Require Reason Code')
                            ->default(true)
                            ->inline(false),
                    ]),

                Section::make('Physical Inventory')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_physical_inventory')
                            ->label('Physical Inventory Journal')
                            ->default(false)
                            ->inline(false),

                        Toggle::make('calculate_inventory')
                            ->label('Auto-Calculate Expected Qty')
                            ->default(false)
                            ->inline(false),

                        Toggle::make('items_not_on_inventory')
                            ->label('Allow Zero-Expected Items')
                            ->default(false)
                            ->inline(false),
                    ]),

                Section::make('Defaults')
                    ->columns(2)
                    ->schema([
                        Select::make('default_adjustment_account_id')
                            ->label('Default Adjustment Account')
                            ->relationship('defaultAdjustmentAccount', 'name')
                            ->searchable()
                            ->preload(),

                        Toggle::make('test_report_before_posting')
                            ->label('Test Report Before Posting')
                            ->default(false)
                            ->inline(false),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }
}
