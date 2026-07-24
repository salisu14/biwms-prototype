<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferralCommissionSettings\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReferralCommissionSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('business.name')->searchable()->sortable(),
                IconColumn::make('is_enabled')->boolean()->label('Enabled'),
                TextColumn::make('default_commission_basis')->badge(),
                TextColumn::make('defaultPlan.code')->label('Default Plan')->placeholder('—'),
                TextColumn::make('commission_decimal_places')->label('Decimals'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
