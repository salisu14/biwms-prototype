<?php

namespace App\Filament\Resources\ProductionJournalBatches\Schemas;

use App\Enums\JournalBatchStatus;
use App\Models\ProductionJournalBatch;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductionJournalBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Batch Identification')
                    ->description('Primary naming and ownership for this production journal workspace.')
                    ->columns(2)
                    ->schema([
                        Select::make('template_id')
                            ->label('Journal Template')
                            ->relationship('template', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (?ProductionJournalBatch $record) => $record !== null)
                            ->dehydrated(),

                        TextInput::make('name')
                            ->label('Batch Name')
                            ->required()
                            ->maxLength(50)
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->placeholder('e.g., ASSEMBLY-LINE-A'),

                        TextInput::make('description')
                            ->label('Description')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make('Production Context')
                    ->description('Link this batch to a specific order or dimensional scope.')
                    ->columns(2)
                    ->schema([
                        Select::make('production_order_id')
                            ->label('Production Order')
                            ->relationship('productionOrder', 'document_number')
                            ->searchable()
                            ->preload()
                            ->helperText('Associating an order will help pre-fill journal lines.'),

                        Select::make('assigned_user_id')
                            ->label('Assigned Specialist')
                            ->relationship('assignedUser', 'name')
                            ->default(auth()->id())
                            ->searchable()
                            ->preload(),

                        TextInput::make('reason_code')
                            ->label('Reason Code')
                            ->placeholder('e.g., OVERRUN'),

                        Toggle::make('auto_post_on_release')
                            ->label('Auto-Post on Release')
                            ->helperText('Automatically post lines when the batch status is changed to Released.')
                            ->default(false)
                            ->inline(false),
                    ]),

                Section::make('Status & Settings')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->options(JournalBatchStatus::class)
                            ->default(JournalBatchStatus::OPEN)
                            ->required()
                            ->native(false),

                        TagsInput::make('dimension_filter')
                            ->label('Dimension Filter')
                            ->placeholder('Add dimension codes...')
                            ->helperText('Restrict this batch to specific cost centers or projects.'),
                    ]),
            ]);
    }
}
