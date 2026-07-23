<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerReferrals\Tables;

use App\Enums\CustomerReferralStatus;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CustomerReferralsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.name')->label('Customer')->searchable()->sortable(),
                TextColumn::make('referrer.name')->label('Referrer')->searchable()->sortable(),
                TextColumn::make('status')->badge()->color(fn ($state) => $state?->color())->sortable(),
                IconColumn::make('is_primary')->boolean()->label('Primary'),
                TextColumn::make('referred_at')->date()->sortable(),
                TextColumn::make('effective_from')->date()->sortable(),
                TextColumn::make('effective_to')->date()->placeholder('Open')->sortable(),
                TextColumn::make('referral_source')->searchable()->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(CustomerReferralStatus::class),
                TernaryFilter::make('is_primary')->label('Primary'),
                SelectFilter::make('referrer_id')->relationship('referrer', 'name')->searchable()->preload(false),
                SelectFilter::make('customer_id')->relationship('customer', 'name')->searchable()->preload(false),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
