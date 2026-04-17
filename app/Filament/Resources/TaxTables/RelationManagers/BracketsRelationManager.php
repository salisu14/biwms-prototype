<?php

namespace App\Filament\Resources\TaxTables\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
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
                TextInput::make('from_amount')
                    ->required()
                    ->numeric(),
                TextInput::make('to_amount')
                    ->numeric(),
                TextInput::make('rate')
                    ->required()
                    ->numeric()
                    ->suffix('%'),
                TextInput::make('base_tax')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('rate')
            ->columns([
                TextColumn::make('from_amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('to_amount')
                    ->numeric()
                    ->placeholder('Infinity')
                    ->sortable(),
                TextColumn::make('rate')
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('base_tax')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
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
