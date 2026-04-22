<?php

namespace App\Filament\Resources\ProductionJournalTemplates\Schemas;

use App\Models\ChartOfAccount;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class ProductionJournalTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Template Settings')
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

                                    Select::make('journal_type')
                                        ->label('Journal Purpose')
                                        ->options([
                                            'consumption' => 'Consumption (Components)',
                                            'output' => 'Output (Finished Goods)',
                                            'capacity' => 'Capacity (Time/Labor)',
                                        ])
                                        ->required()
                                        ->native(false),

                                    TextInput::make('description')
                                        ->maxLength(100)
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

                        Tabs\Tab::make('Automation & Flushing')
                            ->icon('heroicon-o-bolt')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('flushing_method_filter')
                                        ->label('Flushing Method Scope')
                                        ->options([
                                            'all' => 'All Methods',
                                            'manual' => 'Manual Only',
                                            'forward' => 'Forward Only',
                                            'backward' => 'Backward Only',
                                        ])
                                        ->required()
                                        ->native(false),

                                    Toggle::make('allow_flushing_override')
                                        ->label('Allow Override on Lines')
                                        ->helperText('Allow users to change the flushing method during entry.')
                                        ->inline(false),
                                ]),

                                Section::make('Background Posting')
                                    ->columns(2)
                                    ->schema([
                                        Toggle::make('auto_post_output')
                                            ->label('Auto-Post Output')
                                            ->helperText('Post finished goods immediately upon registration.'),

                                        Toggle::make('auto_post_consumption')
                                            ->label('Auto-Post Consumption')
                                            ->helperText('Post material usage automatically.'),
                                    ]),

                                Section::make('Granularity Controls')
                                    ->columns(3)
                                    ->schema([
                                        Toggle::make('post_capacity')->label('Record Capacity'),
                                        Toggle::make('post_time')->label('Record Labor Time'),
                                        Toggle::make('post_quantity')->label('Record Quantities'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Posting & Financials')
                            ->icon('heroicon-o-building-library')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('number_series_id')
                                        ->relationship('numberSeries', 'code')
                                        ->searchable()
                                        ->preload()
                                        ->required(),

                                    Select::make('posting_number_series_id')
                                        ->relationship('postingNumberSeries', 'code')
                                        ->label('Posting No. Series')
                                        ->searchable()
                                        ->preload(),

                                    TextInput::make('source_code')
                                        ->placeholder('e.g., PRODJNL')
                                        ->maxLength(20),

                                    Toggle::make('consolidate_lines')
                                        ->label('Consolidate G/L Entries')
                                        ->helperText('Summarize similar posting lines to reduce ledger density.')
                                        ->inline(false),
                                ]),

                                Section::make('Costing & WIP')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('default_wip_account_id')
                                                ->label('Default WIP Account')
                                                ->relationship('defaultWipAccount', 'account_number')
                                                ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->account_number} – {$record->name}")
                                                ->searchable()
                                                ->preload(),

                                            Select::make('overhead_rate_source')
                                                ->options([
                                                    'work_center' => 'From Work Center',
                                                    'item' => 'From Item Card',
                                                    'standard' => 'System Standard',
                                                ])
                                                ->required()
                                                ->native(false),

                                            Toggle::make('force_wip_account')->label('Force WIP Account'),
                                            Toggle::make('use_production_order_account_setup')->label('Use Order Specific Setup'),
                                            Toggle::make('absorb_overhead')->label('Absorb Overhead Costs')->columnSpanFull(),
                                        ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Validation & Dimensions')
                            ->icon('heroicon-o-check-badge')
                            ->schema([
                                Grid::make(1)->schema([
                                    Toggle::make('copy_from_production_order')
                                        ->label('Inherit Dimension Set')
                                        ->helperText('Automatically pull dimensions from the originating Production Order.'),

                                    TagsInput::make('mandatory_dimensions')
                                        ->label('Required Dimensions')
                                        ->placeholder('Add dimension codes...')
                                        ->helperText('Codes that must be present before posting.'),

                                    TagsInput::make('default_dimensions')
                                        ->label('Default Dimensions')
                                        ->placeholder('Add default codes...'),
                                ]),
                            ]),
                    ]),
            ]);
    }
}
