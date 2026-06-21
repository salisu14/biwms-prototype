<?php

namespace App\Filament\Resources\JournalLines\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class JournalLineInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transaction Details')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('line_no')->label('Line No.'),
                            TextEntry::make('status')->badge()->color(fn (string $state): string => match ($state) {
                                'Posted' => 'success',
                                'Open' => 'warning',
                                default => 'gray',
                            }),
                            TextEntry::make('posting_date')->date(),
                        ]),
                    ]),
                Section::make('Financial Data')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('amount')->money('USD'),
                            TextEntry::make('debit_amount')->money('USD'),
                            TextEntry::make('credit_amount')->money('USD'),
                        ]),
                    ]),
            ]);
    }
}
