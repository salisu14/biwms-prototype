<?php

namespace App\Filament\Resources\CompanyInformation\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;

class CompanyInformationTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->disk('public')
                    ->imageSize(60) // Keeps the image small and uniform
                    ->circular()
                    ->defaultImageUrl(url('/images/default-logo.png')), // Optional: fallback
                //                ViewColumn::make('logo')
                //                    ->label('Logo')
                //                    ->view('filament.tables.columns.company-logo'),

                TextColumn::make('company_name')
                    ->label('Company')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('email')
                    ->label('Contact Email')
                    ->searchable()
                    ->icon('heroicon-m-envelope'),

                TextColumn::make('city')
                    ->searchable(),

                TextColumn::make('base_currency_code')
                    ->label('Base Currency')
                    ->badge()
                    ->color('info'),

                TextColumn::make('fiscal_year_start_month')
                    ->label('FY Start')
                    ->formatStateUsing(fn (string $state): string => date('F', mktime(0, 0, 0, (int) $state, 10))),

                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->label('Manage'),
            ]);
    }
}
