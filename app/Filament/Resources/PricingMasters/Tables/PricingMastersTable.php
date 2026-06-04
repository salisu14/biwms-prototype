<?php

namespace App\Filament\Resources\PricingMasters\Tables;

use App\Enums\PricingStatus;
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
use Illuminate\Support\Number;

class PricingMastersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('price_list_code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->description),

                TextColumn::make('pricingGroup.code')
                    ->label('Pricing Group')
                    ->searchable()
                    ->sortable()
                    ->default('—')
                    ->toggleable(),

                TextColumn::make('customer.customer_number')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->default('—')
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ACTIVE' => 'success',
                        'DRAFT' => 'gray',
                        'PENDING_APPROVAL' => 'warning',
                        'EXPIRED' => 'danger',
                        'CANCELLED' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ACTIVE' => 'Active',
                        'DRAFT' => 'Draft',
                        'PENDING_APPROVAL' => 'Pending Approval',
                        'EXPIRED' => 'Expired',
                        'CANCELLED' => 'Cancelled',
                        default => str_replace('_', ' ', $state),
                    })
                    ->sortable(),

                TextColumn::make('price_list_type')
                    ->label('Applies To')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ALL_CUSTOMERS' => 'All Customers',
                        'CUSTOMER' => 'Specific Customer',
                        'CUSTOMER_GROUP' => 'Customer Group',
                        'CAMPAIGN' => 'Campaign / Promo',
                        'TRANSFER' => 'Transfer',
                        default => str_replace('_', ' ', $state),
                    })
                    ->searchable(),

                TextColumn::make('item.item_code')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->item?->description),

                TextColumn::make('price_type')
                    ->label('Pricing Method')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'UNIT_PRICE' => 'Fixed Unit Price',
                        'PERCENT_DISCOUNT' => 'Discount %',
                        'AMOUNT_DISCOUNT' => 'Discount Amount',
                        'COST_PLUS_PERCENT' => 'Cost + %',
                        'COST_PLUS_AMOUNT' => 'Cost + Amount',
                        'FORMULA' => 'Formula',
                        default => str_replace('_', ' ', $state),
                    })
                    ->searchable(),

                TextColumn::make('unit_price')
                    ->label('Price / Disc.')
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        if ($record->price_type === 'UNIT_PRICE') {
                            return Number::currency((float) $record->unit_price, $record->currency_code);
                        }

                        if ($record->price_type === 'PERCENT_DISCOUNT') {
                            return $record->discount_percent.'%';
                        }

                        if ($record->price_type === 'AMOUNT_DISCOUNT') {
                            return '-'.Number::currency((float) $record->discount_amount, $record->currency_code);
                        }

                        return '-';
                    }),

                TextColumn::make('start_date')
                    ->label('Starts')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Ends')
                    ->date('d/m/Y')
                    ->sortable()
                    ->default('Perpetual')
                    ->color(fn ($record) => $record->end_date && $record->end_date < now() ? 'danger' : 'gray'),

                IconColumn::make('allow_quantity_breaks')
                    ->label('Breaks')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_current_version')
                    ->label('Current')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('priority')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options(PricingStatus::options())
                    ->native(false),
                SelectFilter::make('price_list_type')
                    ->options(['ALL_CUSTOMERS' => 'All Customers', 'CUSTOMER' => 'Customer', 'CUSTOMER_GROUP' => 'Group', 'CAMPAIGN' => 'Campaign']),
                SelectFilter::make('item_id')
                    ->label('Item')
                    ->relationship('item', 'item_code')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_current_version')->label('Current Version'),
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
            ])
            ->defaultSort('priority', 'desc');
    }
}
