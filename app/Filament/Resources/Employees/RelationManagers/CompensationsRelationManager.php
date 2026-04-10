<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompensationsRelationManager extends RelationManager
{
    protected static string $relationship = 'compensations';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\DatePicker::make('effective_date')
                    ->required(),
                \Filament\Forms\Components\TextInput::make('base_salary')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                \Filament\Forms\Components\TextInput::make('reason_code')
                    ->maxLength(255)
                    ->placeholder('e.g. ANNUAL_RAISE, PROMOTION'),
                \Filament\Forms\Components\TextInput::make('job_title')
                    ->maxLength(255)
                    ->placeholder('New Job Title (Optional)'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('effective_date')
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('effective_date')
                    ->date()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('base_salary')
                    ->money()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('reason_code')
                    ->searchable(),
                \Filament\Tables\Columns\TextColumn::make('job_title')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
