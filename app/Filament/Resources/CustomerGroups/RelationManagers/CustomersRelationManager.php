<?php

namespace App\Filament\Resources\CustomerGroups\RelationManagers;

use App\Filament\Resources\CustomerGroups\CustomerGroupResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomersRelationManager extends RelationManager
{
    protected static string $relationship = 'customers';

    protected static ?string $relatedResource = CustomerGroupResource::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Customers';

    public function form(Schema $schema): Schema
    {
        // Form is not needed for read-only relation manager
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('customer_number')
                    ->label('Account #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label('Customer Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email Address')
                    ->icon('heroicon-m-envelope')
                    ->searchable(),

                TextColumn::make('balance')
                    ->label('Current Balance')
                    ->money()
                    ->alignment('right')
                    ->sortable(),

                IconColumn::make('blocked')
                    ->label('Blocked')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // No Create/Attach actions to keep it read-only
            ])
            ->recordActions([
//                ViewAction::make(),
            ])
            ->toolbarActions([
                // No bulk actions to keep it read-only
            ]);
    }
}
