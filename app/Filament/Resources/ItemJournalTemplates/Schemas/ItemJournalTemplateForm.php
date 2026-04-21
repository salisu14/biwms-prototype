<?php

namespace App\Filament\Resources\ItemJournalTemplates\Schemas;

use App\Enums\JournalLineType;
use App\Models\ChartOfAccount;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class ItemJournalTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Template Configuration')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('General')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('name')
                                        ->label('Template Name')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->maxLength(50)
                                        ->extraInputAttributes(['style' => 'text-transform: uppercase']),

                                    Select::make('default_entry_type')
                                        ->options(JournalLineType::class)
                                        ->required()
                                        ->native(false),

                                    TextInput::make('description')
                                        ->maxLength(255)
                                        ->columnSpanFull(),

                                    Toggle::make('is_active')
                                        ->label('Enabled')
                                        ->default(true)
                                        ->inline(false),

                                    Toggle::make('test_report_before_posting')
                                        ->label('Require Test Report')
                                        ->inline(false),
                                ]),
                            ]),

                        Tabs\Tab::make('Numbering & Posting')
                            ->icon('heroicon-o-hashtag')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('number_series_id')
                                        ->relationship('numberSeries', 'code')
                                        ->searchable()
                                        ->preload()
                                        ->required(),

                                    Select::make('posting_number_series_id')
                                        ->relationship('postingNumberSeries', 'code') // Assuming relationship exists
                                        ->label('Posting Number Series')
                                        ->searchable()
                                        ->preload(),

                                    TextInput::make('source_code')
                                        ->maxLength(20)
                                        ->placeholder('e.g., ITEMJNL'),

                                    TextInput::make('reason_code')
                                        ->maxLength(20)
                                        ->placeholder('e.g., CORRECTION'),
                                ]),

                                Section::make('Inventory Posting')
                                    ->schema([
                                        Select::make('default_inventory_account_id')
                                            ->label('Default Inventory G/L Account')
                                            ->relationship('defaultInventoryAccount', 'account_number')
                                            ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->account_number} – {$record->name}")
                                            ->searchable()
                                            ->preload(),

                                        Toggle::make('force_inventory_account')
                                            ->label('Force Default G/L Account')
                                            ->helperText('Prevents users from changing the G/L account on journal lines.'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Validation Rules')
                            ->icon('heroicon-o-check-badge')
                            ->schema([
                                Grid::make(3)->schema([
                                    Section::make('Tracking')
                                        ->columnSpan(1)
                                        ->schema([
                                            Toggle::make('item_tracking_mandatory')->label('Item Tracking'),
                                            Toggle::make('lot_mandatory')->label('Lot No. Mandatory'),
                                            Toggle::make('serial_no_mandatory')->label('Serial No. Mandatory'),
                                            Toggle::make('expiration_date_mandatory')->label('Expiration Mandatory'),
                                        ]),

                                    Section::make('Warehouse')
                                        ->columnSpan(1)
                                        ->schema([
                                            Toggle::make('warehouse_location_mandatory')->label('Location Mandatory'),
                                            Toggle::make('bin_mandatory')->label('Bin Mandatory'),
                                            Toggle::make('check_warehouse_availability')->label('Check Availability'),
                                        ]),

                                    Section::make('Logic')
                                        ->columnSpan(1)
                                        ->schema([
                                            Toggle::make('allow_negative_inventory')->label('Allow Negative Inv.'),
                                            Toggle::make('costing_per_entry')->label('Costing Per Entry'),
                                        ]),
                                ]),
                            ]),

                        Tabs\Tab::make('Filters & Dimensions')
                            ->icon('heroicon-o-funnel')
                            ->schema([
                                Grid::make(2)->schema([
                                    TagsInput::make('mandatory_dimensions')
                                        ->placeholder('Add dimension codes...'),

                                    TagsInput::make('default_dimensions')
                                        ->placeholder('Default dimension values...'),

                                    TagsInput::make('allowed_item_categories')
                                        ->placeholder('Limit to categories...'),

                                    TagsInput::make('blocked_item_nos')
                                        ->placeholder('Restrict specific items...'),
                                ]),
                            ]),
                    ]),
            ]);
    }
}
