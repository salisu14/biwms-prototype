<?php

namespace App\Filament\Resources\DocumentHeaders\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class DocumentHeaderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('doc_type')
                    ->required(),
                TextInput::make('doc_no')
                    ->required(),
                DatePicker::make('doc_date')
                    ->required(),
                DatePicker::make('posting_date')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('OPEN'),
                TextInput::make('created_by')
                    ->required()
                    ->numeric(),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
