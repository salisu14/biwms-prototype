<?php

namespace App\Filament\Resources\FAJournalBatches\Schemas;

use App\Models\FAJournalBatch;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FAJournalBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Batch Identification')
                    ->description('Primary naming and ownership for this Fixed Asset journal workspace.')
                    ->columns(2)
                    ->schema([
                        Select::make('template_id')
                            ->label('Journal Template')
                            ->relationship('template', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (?FAJournalBatch $record) => $record !== null)
                            ->dehydrated(),

                        TextInput::make('name')
                            ->label('Batch Name')
                            ->required()
                            ->maxLength(50)
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->placeholder('e.g., DEPR-APR-26'),

                        TextInput::make('description')
                            ->label('Description')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make('Posting Configuration')
                    ->description('Set default values for transactions within this batch.')
                    ->columns(2)
                    ->schema([
                        Select::make('depreciation_book_id')
                            ->label('Depreciation Book')
                            ->relationship('depreciationBook', 'code')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Transactions in this batch will post to this book by default.'),

                        DatePicker::make('posting_date')
                            ->label('Default Posting Date')
                            ->required()
                            ->default(now())
                            ->native(false),

                        Toggle::make('calculate_depreciation')
                            ->label('Enable Automatic Depreciation')
                            ->helperText('If enabled, the system will suggest depreciation amounts based on asset rules.')
                            ->default(false)
                            ->inline(false),
                    ]),

                Section::make('Management')
                    ->columns(2)
                    ->schema([
                        Select::make('assigned_user_id')
                            ->label('Assigned User')
                            ->relationship('assignedUser', 'name')
                            ->searchable()
                            ->preload()
                            ->default(auth()->id()),

                        Select::make('status')
                            ->options([
                                'open' => 'Open',
                                'released' => 'Released',
                                'posted' => 'Posted',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required()
                            ->default('open')
                            ->native(false),
                    ]),
            ]);
    }
}
