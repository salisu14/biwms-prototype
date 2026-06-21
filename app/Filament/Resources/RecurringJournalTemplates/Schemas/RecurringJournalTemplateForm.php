<?php

namespace App\Filament\Resources\RecurringJournalTemplates\Schemas;

use App\Enums\RecurringMethod;
use App\Models\ChartOfAccount;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RecurringJournalTemplateForm
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
                            ->unique(ignoreRecord: true),

                        Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Recurring Schedule')
                    ->columns(2)
                    ->schema([
                        Select::make('recurring_method')
                            ->label('Recurring Method')
                            ->options(RecurringMethod::class)
                            ->required()
                            ->native(false)
                            ->helperText('Fixed — same amount each period. Variable — enter amount before posting. Balance — posts account balance. Reversing — auto-reverses next period.'),

                        Select::make('recurring_frequency')
                            ->label('Frequency')
                            ->options([
                                'daily' => 'Daily',
                                'weekly' => 'Weekly',
                                'monthly' => 'Monthly',
                                'quarterly' => 'Quarterly',
                                'yearly' => 'Yearly',
                            ])
                            ->required()
                            ->native(false),

                        TextInput::make('recurring_interval')
                            ->label('Every N Periods')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->helperText('e.g. 2 = every 2 months'),

                        DatePicker::make('start_date')
                            ->label('Start Date')
                            ->required()
                            ->native(false),

                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->native(false)
                            ->afterOrEqual('start_date'),
                    ]),

                Section::make('Amount & Formula')
                    ->columns(2)
                    ->schema([
                        TextInput::make('fixed_amount')
                            ->label('Fixed Amount')
                            ->numeric()
                            ->helperText('Used when Recurring Method is "Fixed" or "Reversing Fixed".'),

                        Textarea::make('calculation_formula')
                            ->label('Calculation Formula')
                            ->rows(2)
                            ->helperText('e.g. GL(1000).Balance * 0.05 — for Variable/Balance methods.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Posting Controls')
                    ->columns(2)
                    ->schema([
                        Select::make('number_series_id')
                            ->label('Document No. Series')
                            ->relationship('numberSeries', 'code')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('posting_no_series_id')
                            ->label('Posting No. Series')
                            ->relationship('postingNumberSeries', 'code')
                            ->searchable()
                            ->preload(),

                        TextInput::make('source_code')
                            ->label('Source Code')
                            ->maxLength(20),

                        TextInput::make('reversal_days_offset')
                            ->label('Reversal Days Offset')
                            ->numeric()
                            ->default(1)
                            ->helperText('Days after posting to create the reversal entry.'),

                        Select::make('default_balancing_account_id')
                            ->label('Default Bal. Account')
                            ->relationship('defaultBalancingAccount', 'name')
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->no} - {$record->name}"),

                        Toggle::make('auto_post')
                            ->label('Auto Post')
                            ->helperText('Automatically post when due (requires scheduler).')
                            ->inline(false),

                        Toggle::make('auto_reverse')
                            ->label('Auto Reverse')
                            ->helperText('Automatically create a reversal entry after posting.')
                            ->inline(false),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }
}
