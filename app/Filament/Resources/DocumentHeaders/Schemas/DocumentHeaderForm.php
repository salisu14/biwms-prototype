<?php

namespace App\Filament\Resources\DocumentHeaders\Schemas;

use App\Enums\DocumentType;
use App\Enums\DocumentStatus;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DocumentHeaderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Document Details')
                    ->description('Basic identification and timing of the document.')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('doc_type')
                                ->label('Document Type')
                                // FIX: Use mapWithKeys to call the label() method
                                ->options(
                                    collect(DocumentType::cases())
                                        ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                                )
                                ->searchable()
                                ->required()
                                ->disabled(fn ($record) => $record && !DocumentStatus::tryFrom($record->status)?->isEditable()),

                            TextInput::make('doc_no')
                                ->label('Document Number')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->default(fn () => 'DOC-' . now()->format('Ymd-His'))
                                ->disabled(fn ($record) => $record && !DocumentStatus::tryFrom($record->status)?->isEditable()),

                            DatePicker::make('doc_date')
                                ->label('Document Date')
                                ->required()
                                ->default(now())
                                ->native(false)
                                ->disabled(fn ($record) => $record && !DocumentStatus::tryFrom($record->status)?->isEditable()),

                            DatePicker::make('posting_date')
                                ->label('Posting Date')
                                ->required()
                                ->default(now())
                                ->native(false)
                                ->helperText('Date the financial impact takes effect.')
                                ->disabled(fn ($record) => $record && !DocumentStatus::tryFrom($record->status)?->isEditable()),
                        ]),
                    ]),

                Section::make('Control & Notes')
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            // FIX: Use mapWithKeys to call the label() method
                            ->options(
                                collect(DocumentStatus::cases())
                                    ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                            )
                            ->required()
                            ->default('OPEN')
                            ->helperText('Current state of the document.')
                            ->disabled(fn ($record) => $record && !DocumentStatus::tryFrom($record->status)?->isEditable()),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull()
                            ->disabled(fn ($record) => $record && !DocumentStatus::tryFrom($record->status)?->isEditable()),
                    ]),

                Hidden::make('created_by')
                    ->default(auth()->id()),
            ]);
    }
}
