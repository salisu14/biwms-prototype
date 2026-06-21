<?php

namespace App\Filament\Resources\VendorItems\Tables;

use Filament\Actions\Action;
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

class VendorItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('is_currently_effective')
                    ->label('Status')
                    ->state(fn ($record) => $record->is_currently_effective)
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(fn ($record) => $record->is_currently_effective ? 'Active & Effective' : 'Inactive or Expired')
                    ->sortable(false),

                TextColumn::make('vendor.vendor_name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('item.item_code')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->item?->description),

                TextColumn::make('vendor_item_number')
                    ->label('Vendor SKU')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->formatStateUsing(fn ($record) => Number::currency($record->unit_cost, $record->currency?->code ?? 'NGN'))
                    ->sortable(),

                TextColumn::make('lead_time_days')
                    ->label('Lead Time')
                    ->numeric()
                    ->suffix(' days')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('minimum_order_qty')
                    ->label('MOQ')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                IconColumn::make('is_preferred')
                    ->label('Preferred')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->sortable(),

                TextColumn::make('expiry_date')
                    ->label('Valid Until')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn ($record) => $record->expiry_date && $record->expiry_date < now() ? 'danger' : 'gray')
                    ->default('Perpetual'),
            ])
            ->filters([
                TernaryFilter::make('active_and_effective')
                    ->label('Currently Effective')
                    ->placeholder('All Records')
                    ->trueLabel('Active & Effective Only')
                    ->falseLabel('Inactive / Expired')
                    ->queries(
                        true: fn ($query) => $query->activeAndEffective(), // Leverages model scope
                        false: fn ($query) => $query->where('is_active', false)
                            ->orWhere(function ($q) {
                                $q->whereNotNull('expiry_date')->where('expiry_date', '<', now());
                            }),
                    ),

                TernaryFilter::make('is_preferred')
                    ->label('Preferred'),

                SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'vendor_name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),

                Action::make('set_preferred')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Set as Preferred Vendor')
                    ->modalDescription('This will unset any other preferred vendor for this item. Continue?')
                    ->visible(fn ($record) => !$record->is_preferred)
                    ->action(fn ($record) => $record->setAsPreferred()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('vendor.vendor_name', 'asc');
    }
}
