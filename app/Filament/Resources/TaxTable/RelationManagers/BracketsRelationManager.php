<?php

namespace App\Filament\Resources\TaxTable\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BracketsRelationManager extends RelationManager
{
    protected static string $relationship = 'brackets';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)->schema([
                    TextInput::make('from_amount')
                        ->label('Income From')
                        ->required()
                        ->numeric()
                        ->prefix('$')
                        ->placeholder('0.00'),

                    TextInput::make('to_amount')
                        ->label('Income To')
                        ->numeric()
                        ->prefix('$')
                        ->placeholder('Leave empty for infinity'),

                    TextInput::make('rate')
                        ->label('Tax Rate')
                        ->required()
                        ->numeric()
                        ->suffix('%')
                        ->placeholder('e.g., 7.5'),

                    TextInput::make('base_tax')
                        ->label('Base Fixed Tax')
                        ->required()
                        ->numeric()
                        ->default(0)
                        ->prefix('$')
                        ->placeholder('0.00'),
                ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('rate')
            ->columns([
                TextColumn::make('from_amount')
                    ->label('Lower Limit')
                    ->money()
                    ->sortable(),

                TextColumn::make('to_amount')
                    ->label('Upper Limit')
                    ->money()
                    ->placeholder('∞ (Unlimited)')
                    ->sortable(),

                TextColumn::make('rate')
                    ->label('Tax Percentage')
                    ->suffix('%')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('base_tax')
                    ->label('Fixed Base')
                    ->money()
                    ->sortable(),
            ])
            ->defaultSort('from_amount', 'asc')
            ->headerActions([
                CreateAction::make()
                    ->label('Add New Bracket'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
