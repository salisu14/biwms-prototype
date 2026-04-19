<?php

namespace App\Filament\Resources\ApprovalTemplates\RelationManagers;

use App\Filament\Resources\ApprovalTemplates\ApprovalTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class EntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'entries';

    protected static ?string $relatedResource = ApprovalTemplateResource::class;

    protected static ?string $recordTitleAttribute = 'approver_id';

    protected static ?string $title = 'Approval Steps';
    protected static ?string $pluralLabel = 'Approval Steps';

    protected static ?string $label = 'Approval Step';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)->schema([
                    TextInput::make('sequence_no')
                        ->label('Order')
                        ->numeric()
                        ->required()
                        ->default(fn ($livewire) => ($livewire->ownerRecord->entries()->max('sequence_no') ?? 0) + 10),

                    Select::make('approver_type')
                        ->label('Approver Type')
                        ->options([
                            'user' => 'Specific User',
                            'role' => 'Role Based',
                            'hierarchy' => 'Management Hierarchy',
                            'dimension' => 'Dimension Owner',
                        ])
                        ->required()
                        ->native(false)
                        ->reactive(),

                    Select::make('approver_id')
                        ->label('Assigned User')
                        ->relationship('approver', 'name')
                        ->visible(fn ($get) => $get('approver_type') === 'user')
                        ->required(fn ($get) => $get('approver_type') === 'user')
                        ->searchable()
                        ->preload(),
                ]),

                Grid::make(2)->schema([
                    TextInput::make('approver_role')
                        ->label('Approver Role Code')
                        ->placeholder('e.g., FINANCE_DIR')
                        ->visible(fn ($get) => $get('approver_type') === 'role'),

                    TextInput::make('hierarchy_levels')
                        ->label('Levels Above Requestor')
                        ->numeric()
                        ->default(1)
                        ->visible(fn ($get) => $get('approver_type') === 'hierarchy'),

                    TextInput::make('dimension_code')
                        ->label('Dimension Code')
                        ->visible(fn ($get) => $get('approver_type') === 'dimension'),

                    Toggle::make('allow_delegation')
                        ->label('Allow Delegation')
                        ->default(true)
                        ->inline(false),
                ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sequence_no')
                    ->label('Seq.')
                    ->sortable(),

                TextColumn::make('approver_type')
                    ->label('Type')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('approver.name')
                    ->label('Approver')
                    ->placeholder(fn ($record) => $record->approver_role ?? $record->approver_type),

                ToggleColumn::make('allow_delegation')
                    ->label('Delegation'),
            ])
            ->headerActions([
                CreateAction::make()->label('Add Approval Step'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('sequence_no', 'asc');
    }
}
