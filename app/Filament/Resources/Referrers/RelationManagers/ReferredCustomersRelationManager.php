<?php

declare(strict_types=1);

namespace App\Filament\Resources\Referrers\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReferredCustomersRelationManager extends RelationManager
{
    protected static string $relationship = 'customerReferrals';

    protected static ?string $title = 'Referred Customers';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.customer_number')->label('Customer No.')->searchable(),
                TextColumn::make('customer.name')->label('Customer')->searchable(),
                TextColumn::make('status')->badge()->color(fn ($state) => $state?->color()),
                IconColumn::make('is_primary')->boolean()->label('Primary'),
                TextColumn::make('effective_from')->date(),
                TextColumn::make('effective_to')->date()->placeholder('Open'),
            ])
            ->defaultSort('effective_from', 'desc');
    }
}
