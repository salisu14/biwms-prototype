<?php

namespace App\Filament\Resources\JournalLines\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class JournalLineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('journal_batch_id')->required()->numeric(),
                            TextInput::make('line_no')->required()->numeric()->default(10000),
                            Select::make('status')->options(['Open' => 'Open', 'Posted' => 'Posted', 'Reversed' => 'Reversed'])->default('Open')->required(),
                        ]),
                        Grid::make(2)->schema([
                            DatePicker::make('posting_date')->required(),
                            DatePicker::make('document_date'),
                        ]),
                    ]),

                Section::make('Account & Documentation')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('document_type'),
                            TextInput::make('document_no'),
                            TextInput::make('external_document_no'),
                        ]),
                        Grid::make(2)->schema([
                            TextInput::make('account_type')->required(),
                            TextInput::make('account_no')->required(),
                        ]),
                        Textarea::make('description')->required()->columnSpanFull(),
                    ]),

                Section::make('Financials')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('amount')->numeric()->prefix('$')->default(0),
                            TextInput::make('debit_amount')->numeric()->prefix('$')->default(0),
                            TextInput::make('credit_amount')->numeric()->prefix('$')->default(0),
                        ]),
                        Grid::make(3)->schema([
                            TextInput::make('currency_code'),
                            TextInput::make('currency_factor')->numeric()->default(1),
                            TextInput::make('amount_lcy')->numeric()->prefix('$')->default(0),
                        ]),
                    ]),
            ]);
    }
}
