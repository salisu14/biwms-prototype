<?php

namespace App\Filament\Resources\CompanyInformation\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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

                TextColumn::make('company_name')
                    ->label('Company')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('business.name')
                    ->label('Business')
                    ->badge()
                    ->color(fn($record): string => (int)session('active_business_id', 0) === (int)($record->business_id ?? 0) ? 'success' : 'gray')
                    ->description(fn($record): ?string => (int)session('active_business_id', 0) === (int)($record->business_id ?? 0) ? 'Active' : null),

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
                    ->formatStateUsing(fn(string $state): string => date('F', mktime(0, 0, 0, (int)$state, 10))),

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
                ActionGroup::make([
                    Action::make('set_active')
                        ->label('Set Active')
                        ->icon('heroicon-m-check-circle')
                        ->color('success')
                        // ✅ Only show if this business is NOT the currently active one
                        ->visible(fn($record): bool => $record->business_id !== session('active_business_id'))
                        ->action(function ($record): void {
                            session(['active_business_id' => $record->business_id]);
                        }),

                    ViewAction::make(),
                    EditAction::make()
                        ->label('Manage'),
                ]),
            ]);
    }
}
