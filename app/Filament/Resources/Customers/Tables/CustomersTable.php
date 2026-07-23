<?php

namespace App\Filament\Resources\Customers\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Route;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer_number')
                    ->label('Customer')
                    ->sortable()
                    ->searchable()
                    ->weight('bold')
                    ->formatStateUsing(fn ($state, $record): string => "{$record->customer_number} - {$record->name}")
                    ->description(fn ($record): string => $record->group?->code ?? ''),
                TextColumn::make('name')
                    ->label('Name')
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
                    ->toggleable()
                    ->description(fn ($record) => $record->group?->name ?? ''),
                TextColumn::make('location.name')
                    ->label('Location')
                    ->placeholder('N/A')
                    ->description(fn ($record) => $record->location?->code ?? ''),
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
                    ->visible(fn (): bool => static::customerSubledgerRouteName() !== null)
                    ->url(fn ($record): ?string => ($routeName = static::customerSubledgerRouteName()) !== null
                        ? route($routeName, ['customerId' => $record->id])
                        : null),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Delete Selected'),
                ]),
            ]);
    }

    private static function customerSubledgerRouteName(): ?string
    {
        $panelId = Filament::getCurrentPanel()?->getId();

        if ($panelId === null) {
            return null;
        }

        $routeName = "filament.{$panelId}.pages.customer-subledger-summary";

        return Route::has($routeName) ? $routeName : null;
    }
}
