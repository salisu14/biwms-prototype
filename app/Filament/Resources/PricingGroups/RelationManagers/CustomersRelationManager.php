<?php

namespace App\Filament\Resources\PricingGroups\RelationManagers;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomersRelationManager extends RelationManager
{
    protected static string $relationship = 'customers';

    protected static ?string $relatedResource = CustomerResource::class;

    protected static ?string $title = 'Customers';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer_number')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold')
                    ->formatStateUsing(fn ($state, $record): string => "{$record->customer_number} - {$record->name}")
                    ->url(fn ($record): ?string => CustomerResource::getUrl('view', ['record' => $record]))
                    ->description(fn ($record) => $record->name),

                TextColumn::make('name')
                    ->label('Contact')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),

                IconColumn::make('blocked')
                    ->label('Blocked')
                    ->boolean(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
