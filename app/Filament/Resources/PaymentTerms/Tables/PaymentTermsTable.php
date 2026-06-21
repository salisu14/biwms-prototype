<?php

namespace App\Filament\Resources\PaymentTerms\Tables;

use App\Enums\PaymentTermsCalculation;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PaymentTermsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('calculation_type')
                    ->label('Method')
                    ->badge()
                    ->sortable(),
                TextColumn::make('due_date_net_days')
                    ->label('Net Days')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->placeholder('-'),
                TextColumn::make('discount_percent')
                    ->label('Discount')
                    ->suffix('%')
                    ->color('success')
                    ->sortable()
                    ->alignCenter()
                    ->visible(fn ($record) => $record?->discount_allowed),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
                IconColumn::make('blocked')
                    ->label('Blocked')
                    ->boolean()
                    ->trueColor('danger')
                    ->alignCenter(),
                TextColumn::make('discountAccount.name')
                    ->label('Discount Account')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('code')
            ->filters([
                SelectFilter::make('calculation_type')
                    ->options(PaymentTermsCalculation::class),
                TernaryFilter::make('discount_allowed')
                    ->label('Has Cash Discount'),
                TernaryFilter::make('is_active')
                    ->label('Active Status'),
                TernaryFilter::make('blocked')
                    ->label('Blocked Status'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
