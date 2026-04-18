<?php

namespace App\Filament\Resources\AccountSchedules\RelationManagers;

use App\Enums\AccountScheduleAmountType;
use App\Enums\AccountScheduleRowType;
use App\Enums\AccountScheduleTotalingType;
use App\Filament\Resources\AccountSchedules\AccountScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $relatedResource = AccountScheduleResource::class;

    protected static ?string $recordTitleAttribute = 'description';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)->schema([
                    TextInput::make('line_no')
                        ->label('Line Order')
                        ->numeric()
                        ->required()
                        ->default(fn ($livewire) => ($livewire->ownerRecord->lines()->max('line_no') ?? 0) + 10),

                    TextInput::make('row_no')
                        ->label('Row Code')
                        ->maxLength(10)
                        ->placeholder('e.g. 10'),

                    TextInput::make('description')
                        ->label('Label')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(2),
                ]),

                Section::make('Totaling & Logic')
                    ->columns(2)
                    ->schema([
                        Select::make('totaling_type')
                            ->options(AccountScheduleTotalingType::class)
                            ->enum(AccountScheduleTotalingType::class)
                            ->required()
                            ->native(false),

                        TextInput::make('totaling')
                            ->label('Totaling Filter/Formula')
                            ->helperText('G/L Account range (e.g., 1000..1999) or Row formula.')
                            ->maxLength(255),

                        Select::make('row_type')
                            ->options(AccountScheduleRowType::class)
                            ->enum(AccountScheduleRowType::class)
                            ->required()
                            ->native(false),

                        Select::make('amount_type')
                            ->options(AccountScheduleAmountType::class)
                            ->enum(AccountScheduleAmountType::class)
                            ->required()
                            ->native(false),

                        Checkbox::make('show_opposite_sign')
                            ->label('Reverse Sign (+/-)'),
                    ]),

                Section::make('Formatting')
                    ->columns(4)
                    ->schema([
                        TextInput::make('indentation')
                            ->numeric()
                            ->default(0),
                        Checkbox::make('bold'),
                        Checkbox::make('italic'),
                        Checkbox::make('underline'),
                        Checkbox::make('new_page')->label('Page Break After'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_no')
                    ->label('Row')
                    ->width('50px'),

                TextColumn::make('description')
                    ->label('Row Description')
                    ->state(fn ($record) => str_repeat('    ', $record->indentation) . $record->description)
                    ->weight(fn ($record) => $record->bold ? 'bold' : 'normal')
//                    ->italic(fn ($record) => (bool) $record->italic)
                    ->searchable(),

                TextColumn::make('totaling_type')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('totaling')
                    ->label('Accounts/Formula')
                    ->placeholder('-'),

                IconColumn::make('show_opposite_sign')
                    ->label('Rev. Sign')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('row_type')
                    ->label('Values')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Row'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('line_no', 'asc');
    }
}
