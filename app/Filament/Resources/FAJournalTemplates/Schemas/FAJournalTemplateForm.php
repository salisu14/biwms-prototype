<?php

namespace App\Filament\Resources\FAJournalTemplates\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FAJournalTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Identification')
                    ->description('Primary naming and classification for this Fixed Asset journal.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Template Name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->extraInputAttributes(['style' => 'text-transform: uppercase']),

                        Select::make('template_type')
                            ->label('Journal Type')
                            ->options([
                                'acquisition' => 'Acquisition (Fixed Assets)',
                                'depreciation' => 'Depreciation',
                                'revaluation' => 'Revaluation',
                                'disposal' => 'Disposal',
                                'maintenance' => 'Maintenance',
                            ])
                            ->required()
                            ->native(false),

                        TextInput::make('description')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Enabled')
                            ->default(true)
                            ->inline(false),
                    ]),

                Section::make('Numbering & Posting')
                    ->description('Configure how document numbers are assigned and registered.')
                    ->columns(2)
                    ->schema([
                        Select::make('number_series_id')
                            ->label('No. Series')
                            ->relationship('numberSeries', 'code')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('posting_number_series_id')
                            ->label('Posting No. Series')
                            ->relationship('postingNumberSeries', 'code')
                            ->searchable()
                            ->preload()
                            ->helperText('Leave blank to use the standard No. Series.'),

                        TextInput::make('source_code')
                            ->label('Source Code')
                            ->maxLength(20)
                            ->placeholder('e.g., ASSETJNL'),
                    ]),

                Section::make('Default Controls')
                    ->description('Set behavior for batches and lines using this template.')
                    ->columns(2)
                    ->schema([
                        Select::make('default_depreciation_book_id')
                            ->label('Default Depreciation Book')
                            ->relationship('defaultDepreciationBook', 'code')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Toggle::make('test_report_before_posting')
                            ->label('Require Test Report')
                            ->helperText('Forces validation through a test report before the journal can be posted.')
                            ->default(false)
                            ->inline(false),
                    ]),
            ]);
    }
}
