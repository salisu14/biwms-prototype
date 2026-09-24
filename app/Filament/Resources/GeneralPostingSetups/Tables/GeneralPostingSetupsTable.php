<?php

namespace App\Filament\Resources\GeneralPostingSetups\Tables;

use App\Models\ChartOfAccount;
use App\Models\GeneralPostingSetup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class GeneralPostingSetupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('generalBusinessPostingGroup.code')
                    ->label('Bus. Group')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('generalProductPostingGroup.code')
                    ->label('Prod. Group')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('salesAccount.account_number')
                    ->label('Sales Account')
                    ->placeholder('—')
                    ->toggleable()
                    ->formatStateUsing(fn (GeneralPostingSetup $record): string => self::formatAccount($record->salesAccount)),
                TextColumn::make('cogsAccount.account_number')
                    ->label('COGS Account')
                    ->placeholder('—')
                    ->toggleable()
                    ->formatStateUsing(fn (GeneralPostingSetup $record): string => self::formatAccount($record->cogsAccount)),
                TextColumn::make('inventoryAccount.account_number')
                    ->label('Inventory Account')
                    ->placeholder('—')
                    ->toggleable()
                    ->formatStateUsing(fn (GeneralPostingSetup $record): string => self::formatAccount($record->inventoryAccount)),
                TextColumn::make('purchaseAccount.account_number')
                    ->label('Purchase Account / Clearing (GRNI)')
                    ->placeholder('—')
                    ->toggleable()
                    ->formatStateUsing(fn (GeneralPostingSetup $record): string => self::formatAccount($record->purchaseAccount)),
                IconColumn::make('blocked')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('blocked'),
                SelectFilter::make('general_business_posting_group_id')
                    ->relationship('generalBusinessPostingGroup', 'code')
                    ->label('Business Group'),
                SelectFilter::make('general_product_posting_group_id')
                    ->relationship('generalProductPostingGroup', 'code')
                    ->label('Product Group'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function formatAccount(?ChartOfAccount $account): string
    {
        if (! $account) {
            return '—';
        }

        $number = trim((string) $account->account_number);
        $name = trim((string) $account->name);

        if ($number === '') {
            return $name !== '' ? $name : '—';
        }

        return $name !== '' ? "{$number} — {$name}" : $number;
    }
}
