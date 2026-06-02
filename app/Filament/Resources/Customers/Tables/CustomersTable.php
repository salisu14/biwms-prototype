<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Filament\Pages\Finance\CustomerSubledgerSummary;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer_number')
                    ->label('No.')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('email')
                    ->icon('heroicon-m-envelope')
                    ->toggleable(),
                TextColumn::make('balance')
                    ->money()
                    ->sortable()
                    ->getStateUsing(fn ($record) => $record->balance)
                    ->color(fn ($record) => $record->isOverCreditLimit() ? 'danger' : 'gray'),
                TextColumn::make('credit_limit')
                    ->money()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('blocked')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->blocked ? ($record->blocked_reason ?? 'Blocked') : 'Active')
                    ->color(fn ($record) => $record->blocked ? 'danger' : 'success')
                    ->formatStateUsing(fn ($state) => ucfirst(strtolower($state))),
                TextColumn::make('group.code')
                    ->label('Group')
                    ->toggleable(),
                TextColumn::make('location.name')
                    ->placeholder('N/A'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('blocked')
                    ->label('Blocked Status'),
                SelectFilter::make('customer_posting_group_id')
                    ->label('Posting Group')
                    ->relationship('customerPostingGroup', 'id'),
                SelectFilter::make('location_id')
                    ->label('Location')
                    ->relationship('location', 'name'),
            ])
            ->recordActions([
                Action::make('viewSubledger')
                    ->label('Subledger')
                    ->icon('heroicon-o-book-open')
                    ->color('gray')
                    ->url(fn ($record) => CustomerSubledgerSummary::getUrl([
                        'customerId' => $record->id,
                    ])),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
