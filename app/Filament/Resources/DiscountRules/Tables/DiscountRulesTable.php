<?php

namespace App\Filament\Resources\DiscountRules\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DiscountRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('is_active')
                    ->label('Status')
                    ->state(function ($record) {
                        // Use the model's logic concept to determine state
                        $started = $record->start_date <= now();
                        $expired = $record->end_date && $record->end_date < now();

                        if ($expired) return 'expired';
                        if ($started) return 'active';
                        return 'scheduled';
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'active' => 'heroicon-o-check-circle',
                        'scheduled' => 'heroicon-o-clock',
                        'expired' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'scheduled' => 'info',
                        'expired' => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (string $state): string => ucfirst($state))
                    ->sortable(false),

                TextColumn::make('item.item_code')
                    ->label('Item Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('item.description')
                    ->label('Item Description')
                    ->searchable()
                    ->limit(30)
                    ->toggleable()
                    ->tooltip(fn ($record) => $record->item?->description),

                TextColumn::make('customerGroup.name')
                    ->label('Customer Group')
                    ->searchable()
                    ->sortable()
                    ->badge(),

                TextColumn::make('discount_percent')
                    ->label('Discount')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->sortable()
                    ->alignEnd()
                    ->color('success')
                    ->weight('bold'),

                TextColumn::make('start_date')
                    ->label('Starts')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Ends')
                    ->date('d/m/Y')
                    ->sortable()
                    ->default('—')
                    ->color(fn ($record) => $record->end_date && $record->end_date < now() ? 'danger' : 'gray'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label('Currently Active')
                    ->placeholder('All Rules')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive/Expired')
                    ->queries(
                        true: fn ($query) => $query->active(), // Leverages model scope
                        false: fn ($query) => $query->where('start_date', '>', now())
                            ->orWhere(function ($q) {
                                $q->whereNotNull('end_date')->where('end_date', '<', now());
                            }),
                    ),

                SelectFilter::make('item_id')
                    ->label('Item')
                    ->relationship('item', 'item_code')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('customer_group_id')
                    ->label('Customer Group')
                    ->relationship('customerGroup', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('start_date', 'desc')
            ->emptyStateHeading('No discount rules')
            ->emptyStateDescription('Create discount rules to apply special pricing to customer groups.')
            ->emptyStateIcon('heroicon-o-receipt-percent');
    }
}
