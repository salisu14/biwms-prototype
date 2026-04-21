<?php

namespace App\Filament\Resources\GeneralJournalTemplates\Schemas;

use App\Enums\JournalTemplateType;
use App\Models\ChartOfAccount;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GeneralJournalTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template Identity')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->label('Template Code'),

                        Select::make('template_type')
                            ->label('Type')
                            ->options(JournalTemplateType::class)
                            ->required()
                            ->native(false),

                        Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Number Series')
                    ->columns(2)
                    ->schema([
                        Select::make('number_series_id')
                            ->label('Document No. Series')
                            ->relationship('numberSeries', 'code')
                            ->searchable()
                            ->preload(),

                        Select::make('posting_number_series_id')
                            ->label('Posting No. Series')
                            ->relationship('postingNumberSeries', 'code')
                            ->searchable()
                            ->preload(),
                    ]),

                Section::make('Balancing & Defaults')
                    ->columns(2)
                    ->schema([
                        Select::make('default_balancing_account_id')
                            ->label('Default Bal. Account')
                            ->relationship('defaultBalancingAccount', 'name')
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->no} - {$record->name}"),

                        TextInput::make('source_code')
                            ->label('Source Code')
                            ->maxLength(10),

                        TextInput::make('reason_code')
                            ->label('Reason Code')
                            ->maxLength(10),
                    ]),

                Section::make('Posting Controls')
                    ->columns(2)
                    ->schema([
                        Toggle::make('force_balancing_account')
                            ->label('Force Balancing Account')
                            ->helperText('Require a balancing account on every line.'),

                        Toggle::make('copy_dimensions_from_batch')
                            ->label('Copy Dimensions from Batch'),

                        Toggle::make('suggest_balancing_amount')
                            ->label('Suggest Balancing Amount'),

                        Toggle::make('check_amount_sign')
                            ->label('Check Amount Sign'),

                        Toggle::make('test_report_before_posting')
                            ->label('Test Report Before Posting'),

                        Toggle::make('show_in_role_center')
                            ->label('Show in Role Center')
                            ->default(true),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),
            ]);
    }
}
