<?php

namespace App\Filament\Resources\NumberSeries\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class NumberSeriesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Series Identity')
                    ->description('Identify the module and purpose of this sequence.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Series Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->placeholder('S-INV')
                            ->helperText('Unique identifier for this series.'),

                        Select::make('module')
                            ->label('Target Module')
                            ->options([
                                'sales' => 'Sales (Invoices, Orders)',
                                'purchase' => 'Purchase (Orders, Receipts)',
                                'inventory' => 'Inventory (Journals, Transfers)',
                                'finance' => 'Finance (G/L, Payments)',
                                'warehouse' => 'Warehouse (Picks, Put-aways)',
                            ])
                            ->required()
                            ->native(false)
                            ->prefixIcon('heroicon-m-rectangle-group')
                            ->helperText('Select the functional area that will use this series.'),

                        TextInput::make('description')
                            ->label('Description')
                            ->required()
                            ->columnSpanFull()
                            ->placeholder('e.g., Sales Invoice sequence for Lagos Warehouse'),
                    ]),

                Section::make('Range & Formatting')
                    ->description('Control the numeric sequence and visual prefix.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('prefix')
                            ->label('Visual Prefix')
                            ->required()
                            ->default('P')
                            ->live()
                            ->placeholder('e.g., INV'),

                        TextInput::make('year')
                            ->label('Current Year')
                            ->numeric()
                            ->default((int) date('Y'))
                            ->live()
                            ->required(),

                        Placeholder::make('sample_preview')
                            ->label('Next Number Preview')
                            ->content(function (Get $get) {
                                $year = $get('year') ?? date('Y');
                                $prefix = $get('prefix') ?? 'P';
                                $next = (int) ($get('current_number') ?? 0) + 1;
                                return sprintf('%d-%s-%05d', $year, $prefix, $next);
                            })
                            ->extraAttributes(['class' => 'font-mono text-primary-600 font-bold bg-primary-50 p-2 rounded border border-primary-100']),

                        TextInput::make('starting_number')
                            ->label('Starting No.')
                            ->numeric()
                            ->default(1)
                            ->required(),

                        TextInput::make('current_number')
                            ->label('Last Used No.')
                            ->numeric()
                            ->default(0)
                            ->helperText('The last number issued by the system.')
                            ->live(),

                        TextInput::make('ending_number')
                            ->label('Ending No. (Optional)')
                            ->numeric()
                            ->helperText('Leave empty for infinite series.'),
                    ]),

                Section::make('Usage Rules')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Enabled')
                            ->default(true)
                            ->helperText('Turn off to prevent this series from being used.'),

                        Toggle::make('allow_manual')
                            ->label('Manual Entry Allowed')
                            ->helperText('Allows users to manually override the suggested number.'),
                    ]),
            ]);
    }
}
