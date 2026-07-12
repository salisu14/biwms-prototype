<?php

declare(strict_types=1);

namespace App\Filament\Resources\Employees\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
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
                DatePicker::make('effective_date')
                    ->required(),
                TextInput::make('base_salary')
                    ->required()
                    ->numeric()
                    ->prefix('₦'),
                TextInput::make('reason_code')
                    ->maxLength(255)
                    ->placeholder('e.g. ANNUAL_RAISE, PROMOTION'),
                Textarea::make('audit_note')
                    ->rows(2)
                    ->maxLength(1000)
                    ->placeholder('Why this change was made'),
                TextInput::make('job_title')
                    ->maxLength(255)
                    ->placeholder('New Job Title (Optional)'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('effective_date')
            ->columns([
                TextColumn::make('effective_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('base_salary')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('reason_code')
                    ->searchable(),
                TextColumn::make('audit_note')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('job_title')
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
