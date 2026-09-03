<?php

namespace App\Filament\Resources\Vendors\Tables;

use App\Models\CompanyInformation;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class VendorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vendor_code')
                    ->label('No.')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('vendor_name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                TextColumn::make('balance')
                    ->formatStateUsing(fn ($state, $record): string => Number::currency((float) $state, static::baseCurrencyCode($record->business_id)))
                    ->alignment('right')
                    ->sortable(),

                TextColumn::make('vendorPostingGroup.code')
                    ->label('Group')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('city')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('country')
                    ->searchable()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('blocked')
                    ->label('Blocked')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Modified')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('vendor_posting_group_id')
                    ->label('Posting Group')
                    ->relationship('vendorPostingGroup', 'code'),

                TernaryFilter::make('blocked')
                    ->label('Blocked Status'),

                TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])->defaultSort('vendor_code', 'asc');
    }

    private static function baseCurrencyCode(?int $businessId): string
    {
        return (string) (CompanyInformation::query()
            ->where('business_id', $businessId ?: (int) session('active_business_id', 0))
            ->value('base_currency_code') ?: config('app.base_currency', 'NGN'));
    }
}
