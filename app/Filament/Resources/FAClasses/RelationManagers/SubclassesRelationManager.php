<?php

namespace App\Filament\Resources\FAClasses\RelationManagers;

use App\Filament\Resources\FAClasses\FAClassResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubclassesRelationManager extends RelationManager
{
    protected static string $relationship = 'subclasses';

    protected static ?string $relatedResource = FAClassResource::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Subclasses';
    protected static ?string $modelLabel = 'Subclass';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('code')
                    ->required()
                    ->maxLength(20),

                TextInput::make('name')
                    ->required()
                    ->maxLength(100),

                Select::make('default_posting_group_id')
                    ->label('Default Posting Group')
                    ->relationship('defaultPostingGroup', 'code')
                    ->searchable()
                    ->preload(),

                Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('defaultPostingGroup.code')->label('Posting Group'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
