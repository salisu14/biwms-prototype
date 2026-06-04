<?php

namespace App\Filament\Resources\ItemCharges\Tables;

use App\Models\GeneralProductPostingGroup;
use App\Models\VatProductPostingGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ItemChargesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('Charge No.')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->description_2 ? "{$record->description}\n{$record->description_2}" : $record->description),

                TextColumn::make('description_2')
                    ->label('Description 2')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('gen_prod_posting_group')
                    ->label('Gen. Prod. Post. Group')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('vat_prod_posting_group')
                    ->label('VAT Prod. Post. Group')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('warning'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('gen_prod_posting_group')
                    ->label('Gen. Prod. Posting Group')
                    ->options(GeneralProductPostingGroup::pluck('code', 'code'))
                    ->searchable(),

                SelectFilter::make('vat_prod_posting_group')
                    ->label('VAT Prod. Posting Group')
                    ->options(VatProductPostingGroup::pluck('code', 'code'))
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('number', 'asc')
            ->emptyStateHeading('No item charges')
            ->emptyStateDescription('Create an item charge to categorize additional costs like freight or insurance.')
            ->emptyStateIcon('heroicon-o-receipt-percent');
    }
}
